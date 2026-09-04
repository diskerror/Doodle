# Experiment 1: Tail-Swap Cache Survival

**Question:** If Ragger replaces the "cold" tail of a conversation with a
compact digest, does the Anthropic prompt cache still hit on the
unmodified prefix — i.e. is the swap free on cache cost?

**Result: YES, confirmed empirically 2026-07-24.** Full write-up and
exact commands below so it's repeatable and tweakable.

## Setup

1. Pull a real multi-turn conversation out of Ragger's memory DB:

   ```bash
   cd ~/PhpStormProjects/Doodle
   php doodle contextlab:build-faux-context --turns 12 -o /tmp/faux_tailswap.json
   ```

   This assembled 24 messages (12 real user/assistant turn pairs from
   `~/.ragger/memories.db`), ~3400 tokens. Real turns are used (not
   lorem ipsum) so length/structure matches what Ragger actually sees.

2. **Call A (baseline)** — send the full, unmodified conversation with a
   `cache_control` breakpoint placed after message index 15 (arbitrary
   split point inside the 24-message list), plus the system prompt also
   marked cacheable:

   ```bash
   php doodle contextlab:probe \
     --input /tmp/faux_tailswap.json \
     --cache-at 15 \
     --system-cache \
     --prompt "Continuing the thread, any final thoughts?" \
     --label tailswap-A-baseline \
     --max-tokens 80
   ```

   Result (`usage` block):
   ```json
   {
     "input_tokens": 1601,
     "cache_creation_input_tokens": 3320,
     "cache_read_input_tokens": 0
   }
   ```
   First call — nothing to read yet, 3320 tokens written to cache
   (everything through message 15 + system prompt).

3. **Build the tail-swapped variant.** New helper script
   `ContextLab/make_tail_swap.php` keeps messages `[0..cutIndex]`
   byte-for-byte identical and replaces everything after `cutIndex`
   with a single synthetic "digest" user message:

   ```bash
   php ContextLab/make_tail_swap.php /tmp/faux_tailswap.json 15 /tmp/faux_tailswap_B.json
   ```

   Output: `prefix_messages=16 tail_replaced=8 tail_chars=4343` — kept
   messages 0-15 exactly as sent in Call A, replaced the remaining 8
   messages (~4343 chars of "cold" history) with one digest line.

4. **Call B (swapped)** — same cache_control breakpoint index (15),
   same prefix content, but now the array only has 18 messages total
   (16 original + 1 digest + 1 new prompt) instead of 25:

   ```bash
   php doodle contextlab:probe \
     --input /tmp/faux_tailswap_B.json \
     --cache-at 15 \
     --system-cache \
     --prompt "Continuing the thread, any final thoughts?" \
     --label tailswap-B-swapped \
     --max-tokens 80
   ```

   Result (`usage` block):
   ```json
   {
     "input_tokens": 72,
     "cache_creation_input_tokens": 0,
     "cache_read_input_tokens": 3320
   }
   ```

## Interpretation

- `cache_read_input_tokens: 3320` in Call B exactly matches
  `cache_creation_input_tokens: 3320` from Call A — the **entire
  prefix cache hit**, even though the message list *after* the
  breakpoint was completely different (real tail vs. digest).
- `cache_creation_input_tokens: 0` in Call B — no new cache write was
  needed for the prefix; only the small uncached tail (digest + new
  prompt, `input_tokens: 72`) had to be processed fresh.
- This confirms the core mechanism your compaction strategy depends
  on: **Anthropic's cache is a pure prefix match up to the
  `cache_control` marker.** Content after the marker is irrelevant to
  cache validity for content before it. Ragger can therefore replace
  cold turns after a stable breakpoint with zero cache penalty, as
  long as it never edits anything before that breakpoint.

## Things to tweak next

- **Where exactly does the prefix boundary need to fall?** Try
  swapping starting mid-message-pair vs. only ever at a clean
  user/assistant pair boundary — does Anthropic care about partial
  vs. whole-message edits, or only exact byte match up to the marker?
- **Multiple breakpoints.** Anthropic allows up to 4 `cache_control`
  markers per request. Test nested/staged caching: one marker after
  the system prompt, one after an early "stable" block, one after the
  swap point — see if partial hits are reported per-marker or just
  aggregate.
- **TTL boundary.** Default ephemeral cache is 5 minutes
  (`cache_creation.ephemeral_5m_input_tokens`). Re-run Call B after a
  6+ minute gap and confirm you get a fresh `cache_creation` instead of
  a `cache_read` — this sets the upper bound on how long Ragger can
  wait between turns before the "free" swap window closes. Use
  `--cache-at` unchanged but check the `cache_creation.ephemeral_1h_input_tokens`
  field if you add `"ttl": "1h"` support to `ProbeTask` for a longer
  window (not yet wired up — would need a `--cache-ttl` flag).
- **Real digest quality.** The digest text here is a placeholder
  stand-in (`"[DIGEST replacing N messages...]"`). Swap in an actual
  Ragger-generated summary (pull from `summaries` table where
  `level`/`status` indicate a real compaction) and confirm output
  quality doesn't degrade — this experiment only proved the *cache*
  survives, not that the *model's answers* stay coherent post-swap.
- **Vary cut position relative to conversation size** — does the
  cache-hit behavior change if the swap point is very early (small
  prefix, most of it discarded) vs. very late (large prefix retained,
  tiny tail swapped)? Cost/benefit sweet spot is probably conversation-
  dependent.

## Files

- `ContextLab/make_tail_swap.php` — the splice tool built for this
  experiment. Usage: `php make_tail_swap.php <input.json> <cutIndex> <output.json>`.
  Not (yet) wrapped as a Doodle Task — quick standalone script since it's
  a one-off transform, not a reusable CLI verb. Promote to a Task if you
  end up running variants of this often.
- Every probe run's request+response pair is saved under
  `ContextLab/runs/<timestamp>_<label>/` — the two runs from this
  experiment are `*_tailswap-A-baseline/` and `*_tailswap-B-swapped/`.
