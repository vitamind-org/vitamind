## Why

AI-assisted and human UI work on VitaminD repeatedly re-litigates basic design decisions (e.g. the registration/onboarding hero banner) because there is no authoritative, documented source of truth for design tokens. Two concrete ambiguities exist today in `resources/css/base.css`: `--destructive` and `--danger` both mean "error/danger" with no documented distinction (one is even manually aliased to the other in `resources/js/components/status-ripple.tsx`), and `success`/`warning`/`info`/`gray` have no `.dark` override, unlike every other token in the file. VitaminD is also a boilerplate that downstream developers ("applicators" — e.g. the dogfooding consumer projects tracked in `stabilize-vitamind-packages`) fork wholesale to start their own product; per `MIGRATION_GUIDE.md`, the frontend is not distributed as a package like `vitamind/core`; it is copied at fork time with no live sync afterward. Applicators need a clear, low-risk surface for day-one rebranding, and both AI assistants and human contributors need a written reference that removes guesswork.

## What Changes

- Split `resources/css/base.css` tokens into two tiers: a **system tier** (radius scale ratios, spacing, shadow/elevation, animation timing — the structural contract that stays consistent across every VitaminD-derived product) and a **brand tier** (`--primary`, `--brand`, the `--radius` base value, `--font-sans` — the small set of variables an applicator is expected to edit to rebrand). The brand tier is physically isolated (its own clearly marked block) so applicators and AI both know what is safe to change.
- **BREAKING**: Consolidate `--danger` into `--destructive`. Remove the `--danger` / `--color-danger` token and update its 4 usage sites (`two-factor.tsx`, `discovered.tsx`, `badge.tsx`, `status-ripple.tsx`) to use `destructive` instead.
- Add tuned OKLCH `.dark` overrides for `success`, `warning`, `info`, `gray`, following the same lightness/chroma adjustment pattern already used by `--destructive`'s existing light→dark pair, so these tokens no longer stay pinned to their light-mode value in dark mode.
- Add `docs/design-system.md` documenting the token architecture, the system/brand tier split with an explicit list of which variable lands in which tier, the do/don't rule for editing each tier, and the semantic meaning of each color token (removing the destructive/danger ambiguity going forward).

Component-level usage guidance (e.g. when to use Dialog vs Sheet vs Popover vs DropdownMenu vs Tooltip vs Command) is explicitly out of scope for this change and deferred to a future Phase 2.

## Capabilities

### New Capabilities
- `design-system-tokens`: Defines the two-tier (system/brand) Tailwind design token architecture, the canonical semantic color vocabulary, and the documentation contract (`docs/design-system.md`) that governs how VitaminD and applicator forks customize visual tokens.

### Modified Capabilities
- None. No existing `openspec/specs/` capability covers frontend design tokens.

## Impact

- **Affected code**: `resources/css/base.css` (restructured into tiers, `--danger` removed, dark overrides added), `resources/js/components/ui/badge.tsx`, `resources/js/components/status-ripple.tsx`, `resources/js/pages/profile/components/two-factor.tsx`, `resources/js/pages/plugins/components/discovered.tsx`.
- **New docs**: `docs/design-system.md`.
- **Breaking change**: any code (in this repo or in an applicator fork that has already diverged) referencing the `danger` token, `text-danger`, `bg-danger`, or the `danger` Badge variant will need to switch to `destructive`.
- **No runtime/build tooling changes**: no new CLI, no theming API — the customization mechanism is static CSS tier separation plus documentation.
