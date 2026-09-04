# ContextLab

Experiment space for testing Ragger-driven context compaction concepts
against real provider APIs (starting with Anthropic). Lives in Doodle
so it's plain PHP CLI tasks — no app framework, no Python.

## Goal

Validate before building: can Ragger pre-emptively replace "cold" turns in
a conversation with digests, timed and boundary-placed so that Anthropic's
prompt cache (`cache_control` breakpoints) survives the swap? Compare
against what happens if we let the naive/no-op case run (no compaction,
or a naive mid-history edit) and watch `cache_read_input_tokens` /
`cache_creation_input_tokens` in the response `usage` block.

## Tasks

- `contextlab:build-faux-context` — pull real turns out of
  `~/.ragger/memories.db` (`turns` / `summaries` tables) and assemble a
  synthetic Anthropic `messages[]` array of a requested shape/size. Lets
  us build realistic-but-controlled conversations instead of synthetic
  lorem ipsum, since real turns have the length/structure distribution
  Ragger will actually see.

- `contextlab:probe` — send a directly-constructed request straight to
  the Anthropic Messages API (no Hermes in the loop), with explicit
  `cache_control` placement, and report the full `usage` block
  (`input_tokens`, `cache_creation_input_tokens`, `cache_read_input_tokens`,
  `output_tokens`, `cache_creation.ephemeral_5m_input_tokens`, etc). This
  is the fast micro-experiment path — full control over prefix/breakpoint
  placement, no Hermes overhead.

- `contextlab:hermes-convo` — drives a real multi-turn conversation
  through Hermes itself (`hermes --continue <name> -z "<prompt>"` with
  `HERMES_DUMP_REQUESTS=1`), then parses the resulting request-dump JSON
  from `~/.hermes/sessions/` to show what Hermes actually sent and what
  usage came back. This is the realistic-integration path — confirms
  what happens when Hermes's own compression logic is in the loop
  alongside (or replaced by) a Ragger-driven swap.

## Config

API key is read from `~/.hermes/.env` (`ANTHROPIC_API_KEY`) by default;
override with `--key`. Model defaults to `claude-sonnet-5`; override
with `--model`. Set `--beta context-1m-2025-08-07` to opt into the 1M
window (Sonnet 5 defaults to 200K without it).

## Output

Every task prints JSON to stdout (`JSON_PRETTY_PRINT`) so results can be
piped/diffed/logged. Raw request+response pairs are saved under
`ContextLab/runs/<timestamp>_<label>/` for later comparison.
