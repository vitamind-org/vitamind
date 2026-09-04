## Context

VitaminD's Tailwind v4 tokens live in `resources/css/base.css` (CSS-first `@theme` config, OKLCH color space, `.dark` class-based dark mode via `@custom-variant dark (&:is(.dark *))`). The 42 components in `resources/js/components/ui/` are shadcn-derived (Radix primitives + CVA). VitaminD itself is a boilerplate: downstream "applicator" developers (e.g. the dogfooding consumer projects BukuWarga, LembarUji, UangKas tracked in `stabilize-vitamind-packages`) fork the whole repo to start their own product. Per `MIGRATION_GUIDE.md:108`, the frontend is not distributed as an installable package the way `vitamind/core` is — it is copied once at fork time with no live sync afterward. There is therefore no runtime theming API to design; the deliverable is a static token architecture plus documentation.

Investigation of `base.css` and its 4 consumers found two concrete defects:
- `--destructive` and `--danger` are semantically identical. `badge.tsx` already defines both variants with byte-identical class strings (lines 16-19), and `status-ripple.tsx:13` manually aliases `danger: 'bg-destructive/90'`. No caller anywhere in the codebase actually passes `variant="danger"` to `Badge` — that variant is dead code.
- `success`, `warning`, `info`, `gray` are flat aliases to a single Tailwind palette stop (`--color-lime-500`, etc.) with no `.dark` override, unlike every other token in the file, which all carry a tuned light/dark OKLCH pair.

## Goals / Non-Goals

**Goals:**
- Define a two-tier token architecture (system vs brand) covering every variable currently in `base.css`, so applicators and AI assistants both know unambiguously what is safe to edit and what must stay locked.
- Remove the `destructive`/`danger` duplication, keeping `destructive` as the single canonical name (33 existing usages vs. 4, and the ecosystem-standard shadcn/Radix name).
- Give `success`, `warning`, `info`, `gray` real dark-mode values instead of a flat pass-through.
- Ship `docs/design-system.md` as the authoritative reference document.

**Non-Goals:**
- No component usage catalog (Dialog vs Sheet vs Popover vs DropdownMenu vs Tooltip vs Command) — deferred to a future Phase 2 change.
- No CLI/wizard/build-time tooling for applicators (e.g. a `shadcn init`-style prompt) — considered and rejected in favor of static docs for this phase.
- No redesign of the actual brand color, radius value, or font choice — this change defines *where* those live and *how* they're documented, not what VitaminD's current values should be changed to.

## Decisions

### 1. Two-tier token architecture

Every variable in `base.css` is classified into exactly one tier:

| Tier | Variables | Rule for applicators |
|---|---|---|
| **System (locked)** | `--background`, `--foreground`, `--card`(-foreground), `--popover`(-foreground), `--secondary`(-foreground), `--muted`(-foreground), `--accent`(-foreground), `--destructive`(-foreground), `--border`, `--input`, `--ring`, `--chart-1..5`, `--sidebar`(-foreground/-primary/-primary-foreground/-accent/-accent-foreground/-border/-ring), `--success`, `--warning`, `--info`, `--gray`, the radius **scale ratio** (`--radius-lg`/`-md`/`-sm` = `var(--radius)`, `var(--radius) - 2px`, `var(--radius) - 4px`) | Do not edit. These carry the accessibility contrast pairing (`-foreground` counterparts) and cross-component consistency (chart ramp, sidebar states) that keeps every VitaminD-derived product visually and functionally coherent. |
| **Brand (applicator-editable)** | `--primary`(-foreground), `--brand`, `--radius` (base value only), `--font-sans` | Edit these to rebrand. Isolated in their own marked block in `base.css` (see below) so the edit surface is obvious without reading the whole file. |

`base.css` is restructured so the brand-tier block is a single, clearly commented section near the top of `:root` (and its `.dark` counterpart), physically separated from the system-tier block — not interleaved as it is today. No new file split (single `base.css` stays the source of truth) — a second file was considered and rejected as unnecessary indirection for ~6 variables.

`--primary-foreground` stays paired with `--primary` in the brand tier (an applicator changing `--primary` to a light color needs to be able to flip `--primary-foreground` to dark text, and vice versa) — foreground/background pairs move together as a unit.

### 2. `destructive`/`danger` consolidation

Canonical name: **`destructive`**. Rationale: 33 existing usages vs. 4 for `danger`, it's the shadcn/Radix ecosystem-standard name already threaded through form validation states (`aria-invalid:ring-destructive`), and `badge.tsx` already proves the two are meant to be identical.

Migration is a straight deletion + 3 call-site edits (the 4th "usage", `badge.tsx`'s `danger` variant, has zero callers — see Context):

| File | Change |
|---|---|
| `resources/css/base.css` | Remove `--danger` and `--color-danger` entirely. |
| `resources/js/components/ui/badge.tsx` | Delete the `danger` variant object (lines 16-17) — byte-identical to `destructive`, no callers reference it. |
| `resources/js/components/status-ripple.tsx` | Remove the `danger: 'bg-destructive/90'` alias entry; if any caller passes `status="danger"`, repoint it to `status="destructive"`. |
| `resources/js/pages/profile/components/two-factor.tsx` | `text-danger` → `text-destructive`. |
| `resources/js/pages/plugins/components/discovered.tsx` | `text-danger` → `text-destructive`. |

### 3. Dark-mode values for `success`/`warning`/`info`/`gray`

Rather than hand-deriving new OKLCH values, reuse Tailwind v4's own perceptually-tuned scale: map each light-mode `-500` stop to its `-400` stop for dark mode, the same "one step lighter" relationship Tailwind's own palette already encodes for exactly this purpose. Values pulled directly from `node_modules/tailwindcss/theme.css`:

| Token | Light (`-500`, unchanged) | Dark (new, `-400`) |
|---|---|---|
| `--success` | `oklch(76.8% 0.233 130.85)` (lime-500) | `oklch(84.1% 0.238 128.85)` (lime-400) |
| `--warning` | `oklch(79.5% 0.184 86.047)` (yellow-500) | `oklch(85.2% 0.199 91.936)` (yellow-400) |
| `--info` | `oklch(62.3% 0.214 259.815)` (blue-500) | `oklch(70.7% 0.165 254.624)` (blue-400) |
| `--gray` | `oklch(55.1% 0.027 264.364)` (gray-500) | `oklch(70.7% 0.022 261.325)` (gray-400) |

This mirrors the direction (though not exact magnitude — each hue is independently tuned by Tailwind) of the lightness increase already present in `--destructive`'s existing light→dark pair (`oklch(0.577 0.245 27.325)` → `oklch(0.704 0.191 22.216)`), so the resulting `.dark` block stays visually consistent with the rest of the file.

### 4. `docs/design-system.md` structure

New file, peer to `docs/local-plugins.md`. Sections: (1) token architecture overview with the tier table from Decision 1, (2) "How to rebrand your fork" — a short, applicator-facing walkthrough naming the exact brand-tier variables and where they live in `base.css`, (3) semantic color reference — what each system-tier color token means and when to use it (this is where the `destructive` consolidation is documented so the ambiguity doesn't reappear), (4) a note that component-level usage guidance is Phase 2 / not yet written.

## Risks / Trade-offs

- **[Risk]** The `destructive`/`danger` removal is a breaking rename for any applicator fork that already diverged and added its own `danger` usages. → **Mitigation**: call it out explicitly as **BREAKING** in the proposal; `docs/design-system.md` documents the rename so it's discoverable if an applicator's build breaks.
- **[Risk]** Classifying almost everything except `primary`/`brand`/`radius`/`font-sans` as system-locked is a stricter brand surface than some applicators may want (e.g. they may want their own chart color ramp). → **Mitigation**: this is an intentional, revisitable line — `docs/design-system.md` can loosen it later without a breaking change, since loosening (moving a variable from system to brand) never breaks an existing fork.
- **[Trade-off]** Reusing Tailwind's `-400` stop for dark-mode values instead of hand-tuning bespoke OKLCH values is faster and more consistent with Tailwind's own design intent, but the exact contrast ratio against `--background` dark value was not independently verified against WCAG — flagged as a follow-up spot-check, not a blocker for this change.

## Migration Plan

1. Restructure `base.css`: extract brand-tier block, remove `--danger`, add dark overrides for success/warning/info/gray.
2. Update the 3 real call sites (`badge.tsx`, `status-ripple.tsx`, `two-factor.tsx`, `discovered.tsx` — 4 files, `badge.tsx` needs no caller migration since it has none).
3. Write `docs/design-system.md`.
4. Manual visual smoke check in both light and dark mode (badges, alerts, form validation error states) before merging — no automated visual regression tooling exists in this repo today, so this is a manual step.

No rollback complexity: this is a pure CSS/doc change with no data migration, no feature flag needed. Rollback is a plain revert.

## Open Questions

- `--sidebar-primary` currently does **not** derive from `--primary` (light: `oklch(0.205 0 0)`, a near-black neutral, vs. `--primary`'s purple `oklch(51.1% 0.262 276.966)`) — it's an independently-set neutral tone. Should it (a) stay system-tier and neutral regardless of brand color, or (b) be re-derived from `--primary` so an applicator's brand color propagates to the active sidebar nav state? Recommendation: keep it system/neutral for now (safer default, avoids contrast surprises from arbitrary brand hues), revisit if an applicator asks for it — not resolved in this change's tasks.
