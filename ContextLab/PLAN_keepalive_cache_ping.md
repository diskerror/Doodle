# Plan: Cache Keep-Alive Ping

Status: DESIGN ONLY, nothing built. Confirm primitives (marked ⚠️ TEST) before coding.

## Problem

Anthropic prompt cache dies after idle TTL (5m default, 1h optional). A miss
forces a full-price cache WRITE (1.25x/2x base rate) instead of a cheap READ
(0.1x). Reid thinks out loud for a while between turns — most of that idle
time is wasted cache life.

## Telemetry (state.db, 60-day window)

- 1459 user-turn gaps total. 60% ≤5min (cache survives anyway), 33% land in
  5-60min (dies today, would survive under keep-alive or 1h tier), 7% >60min.
- Real cost at 5m tier: $376.93. Modeled at 1h tier: $287.41 (~24% cheaper).
- Reid's decision: keep TTL at 5m (not 1h) since 93% of gaps are <60min, and
  build keep-alive instead of paying the 2x write multiplier on the 1h tier.

## Break-even math

`ping-worth-it duration = (write_multiplier / read_multiplier) * TTL`

- 5m tier: (1.25/0.1) * 5min = **62.5 min** ceiling on pinging
- 1h tier: (2.0/0.1) * 60min = **20 hours** ceiling (not used — see decision above)

Ping every ~4-5min (before each TTL lapse) up to ~60min of idle, then stop and
accept the eventual full write on next real turn. Past 60min, further pings
cost more than just eating one write.

## What a "keep-alive ping" actually is

NOT: a heartbeat/loop/refine-style turn. Those fire a REAL turn — full agent
loop, tool access, visible assistant reply, appended to stored transcript.
Wrong shape: expensive (real output tokens) and pollutes history with junk
turns just to touch the cache.

NOT: an empty-string user message injected into the transcript. Hermes
enforces strict role alternation and forbids synthetic user messages injected
mid-loop (AGENTS.md doctrine, "cache-breaking mid-conversation" is explicitly
rejected). A fake empty turn saved to history breaks that and permanently
mutates the session for a purely mechanical reason.

IS: a side-channel API call.
- Same messages array as the last real request (system + history up to the
  existing `cache_control` breakpoint), sent byte-identical so it lands as a
  cache READ against the live entry.
- Minimal `max_tokens` (e.g. 1) — we don't want or use the completion, only
  the `usage` block confirming `cache_read_input_tokens` matched.
- Response DISCARDED. Never appended to the stored session, never shown to
  the user, never touches `messages` table in state.db.
- Confirmed empirically safe/free by `EXPERIMENT_01_tail_swap_cache.md` in
  this same directory: Anthropic caches are pure PREFIX matches up to the
  `cache_control` marker; content *after* the marker doesn't matter to the
  hit. A side-call replaying the same prefix is indistinguishable from a real
  turn's cache lookup, from the provider's point of view.

## Required primitive (⚠️ UNCONFIRMED — find or build)

Need: "send this exact request payload to the provider, do NOT append
anything to the stored transcript, discard the reply, just read back
`usage.cache_read_input_tokens`."

Candidates to check before writing new code:
- `agent/conversation_loop.py` — retry/failover logic already re-sends a
  built request without necessarily being the "canonical" turn-send path.
  Does it have a "fire and don't persist" mode, or does persistence happen
  earlier in the call chain (before conversation_loop even runs)?
- `agent/prompt_caching.py` — `build_prompt_cache_plan()` builds the
  request-local messages+cache-marker shape already. This is exactly the
  payload-builder we'd reuse; it's already decoupled from persistence
  (pure function, no AIAgent dependency per its own docstring).
- Doodle's own `contextlab:probe` task (ContextLab/README.md) already does
  a raw, direct-to-Anthropic-Messages-API send with explicit cache_control
  placement and full `usage` reporting, bypassing Hermes entirely. This is
  the FASTEST path to a working prototype — doesn't require touching Hermes
  core at all, just needs to run on a schedule against a live session's
  actual current message array.

⚠️ TEST: does `build_prompt_cache_plan()` output feed directly into
`contextlab:probe`-style raw send, or does it assume more Hermes-internal
state (agent object, tool schemas) than a standalone ping needs?

⚠️ TEST: confirm a side-call using an OLDER cache_control breakpoint index
(matching a prefix from N messages ago, not the very latest) still reads
correctly — i.e. the keep-alive doesn't need to know about turns added AFTER
the last real send, only replay what was already cached.

## Where the timer lives

Needs a per-session idle-watcher: on last real turn, start/reset a timer;
if idle crosses ~4-5min with no new real turn, fire one ping; repeat up to
~60min ceiling; then stop (let it expire, eat the write on next real turn).

Check before building new scheduling infra:
- `gateway_heartbeats` table (seen in state.db schema) — may already track
  per-session liveness/idle state Hermes uses for something else.
- `hermes_cli/heartbeat.py` `HeartbeatState` — per-session recurring timer
  dataclass already exists for `/heartbeat`. Wrong ACTION (fires a real turn)
  but maybe the right TIMER PRIMITIVE to drive a different, non-turn action.

⚠️ TEST: can a `HeartbeatState`-style timer fire an arbitrary callback
instead of "inject a real turn", or is turn-injection baked into that class
and not swappable?

## Slash commands (separate small piece, not blocked by the above)

`/ttl [5m|1h]` — read/write `prompt_caching.cache_ttl` in config.yaml, update
live `agent._cache_ttl`. Same pattern as `/model` (config write + live-agent
update). Handler goes in `gateway/slash_commands.py`
(`GatewaySlashCommandsMixin`), same file as the other ~42 commands.

`/keepalive [on|off]` — new bool config key (e.g.
`prompt_caching.keepalive_enabled`) gating whether the idle-watcher above is
armed for this session.

Ragger has no slash-command surface of its own — it's consumed via MCP tools
(store/search/get_config/status/capture_turn). "Ragger responds to a slash
command" in practice means: a Hermes `/whatever` handler calls a Ragger MCP
tool under the hood. No new mechanism needed there, just wiring.

## Existing related prior art (found in Ragger memory, different session)

An earlier design thread explored:
- `/newcontext`: plugin intercepts via `on_turn_start`, injects Ragger-built
  context as synthetic prefix — simulates fresh session without a real reset.
  True reset (new session id, cleared history, cache reset, autocomplete
  registration) needs core changes mirroring `/new`.
- A `/ttl` variant living entirely in a Ragger plugin (`on_turn_start`
  pattern-match, write to `~/.ragger/settings.ini`, forward-looking only,
  no core Hermes change) — flagged as blocked because Hermes's Anthropic
  provider currently reads a GLOBAL `cache_ttl` from config.yaml, not a
  per-request override; per-request TTL is unimplemented on the provider
  side. Relevant if `/ttl` is scoped as a plugin instead of a core command —
  core `config.yaml` write (this plan's approach) sidesteps that limitation
  since `agent_init.py` already reads `prompt_caching.cache_ttl` at init.

## Next session: work order

1. Read `agent/conversation_loop.py` in full — confirm whether persistence
   (append-to-transcript) happens inside it or strictly before it. This
   decides whether a "send but don't persist" mode is a small conditional or
   requires calling something lower-level directly.
2. Prototype the ping as a **standalone Doodle task** first (extend
   `contextlab:probe` or add `contextlab:keepalive-ping`), pointed at a real
   session's current message array pulled from state.db — proves the
   mechanism without touching Hermes core at all.
3. Only after (2) works: decide whether keep-alive becomes a Hermes-core
   feature (needs the timer + config + persistence-bypass primitives) or
   stays an external Doodle/cron job that periodically poll-and-pings active
   sessions from outside — the latter is far less invasive and may be good
   enough.
4. Write the TTL boundary experiment that was never run (see
   `EXPERIMENT_01_tail_swap_cache.md`, "Things to tweak next" section):
   re-run Call B after 6+ minutes, confirm fresh `cache_creation` vs.
   `cache_read`, to nail down actual observed TTL behavior (see also the
   fuzziness discussion — TTL resets on every read, so measure carefully:
   no intervening calls between write and the delayed read).
