## 1. Restructure `resources/css/base.css`

- [ ] 1.1 Split `:root` into a labeled system-tier block and a labeled brand-tier block (`--primary`, `--primary-foreground`, `--brand`, `--radius`, `--font-sans`); do the same for the `.dark` scope.
- [ ] 1.2 Confirm `--radius-lg`/`--radius-md`/`--radius-sm` in the `@theme` block remain derived from `var(--radius)` (no hardcoded literals) after the restructure.
- [ ] 1.3 Remove `--danger` from `:root` and remove `--color-danger` from the `@theme` block.
- [ ] 1.4 Add `.dark` overrides: `--success: oklch(84.1% 0.238 128.85)`, `--warning: oklch(85.2% 0.199 91.936)`, `--info: oklch(70.7% 0.165 254.624)`, `--gray: oklch(70.7% 0.022 261.325)`.

## 2. Consolidate `danger` → `destructive` in components

- [ ] 2.1 `resources/js/components/ui/badge.tsx`: delete the `danger` variant object from `badgeVariants` (duplicate of `destructive`, zero callers).
- [ ] 2.2 `resources/js/components/status-ripple.tsx`: remove the `danger: 'bg-destructive/90'` alias entry; repoint any `status="danger"` callers to `status="destructive"`.
- [ ] 2.3 `resources/js/pages/profile/components/two-factor.tsx`: change `text-danger` to `text-destructive`.
- [ ] 2.4 `resources/js/pages/plugins/components/discovered.tsx`: change `text-danger` to `text-destructive`.
- [ ] 2.5 Grep `resources/js` for any remaining `danger` reference (`text-danger`, `bg-danger`, `border-danger`, `variant="danger"`) and resolve any that surface.

## 3. Write `docs/design-system.md`

- [ ] 3.1 Write the token architecture overview section (system tier vs brand tier, full variable list per tier).
- [ ] 3.2 Write the "How to rebrand your fork" applicator walkthrough naming the exact brand-tier variables and their location in `base.css`.
- [ ] 3.3 Write the semantic color reference section, documenting each system-tier color token's meaning, explicitly stating `destructive` is the sole error/danger color.
- [ ] 3.4 Add a note that component-level usage guidance (Dialog vs Sheet vs Popover, etc.) is Phase 2 and not yet covered.

## 4. Verify

- [ ] 4.1 Run `npm run types` to confirm the `badge.tsx` and other component edits still type-check.
- [ ] 4.2 Manually smoke-test in the browser, both light and dark mode: a `Badge` in each variant, an `Alert`, a form field in an invalid/error state, and any surface using `status-ripple.tsx`.
- [ ] 4.3 Confirm the three spec scenarios are satisfiable: no `--danger`/`--color-danger` in `base.css`, no `danger` usage left in `resources/js`, `.dark` overrides present for `success`/`warning`/`info`/`gray`.
