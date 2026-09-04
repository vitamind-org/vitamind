## ADDED Requirements

### Requirement: Two-tier token architecture
`resources/css/base.css` SHALL separate design tokens into exactly two tiers: a system tier (structural/neutral tokens that SHALL NOT vary between VitaminD-derived products) and a brand tier (`--primary`, `--primary-foreground`, `--brand`, `--radius`, `--font-sans`) that applicator forks are expected to edit to rebrand. The brand tier SHALL be physically isolated in its own clearly marked block, separate from system-tier declarations, in both the `:root` and `.dark` scopes.

#### Scenario: Brand tier is isolated and identifiable
- **WHEN** a developer opens `resources/css/base.css`
- **THEN** the brand-tier variables (`--primary`, `--primary-foreground`, `--brand`, `--radius`, `--font-sans`) appear in a single contiguous, labeled block distinct from the system-tier variables

#### Scenario: Radius scale ratio stays derived, not hardcoded
- **WHEN** the `@theme` block defines `--radius-lg`, `--radius-md`, `--radius-sm`
- **THEN** each is expressed as a function of `var(--radius)` (not a hardcoded literal), so changing the brand-tier `--radius` base value propagates through the whole scale

### Requirement: Canonical destructive semantic color
The design token set SHALL use `destructive` as the sole canonical name for the "error/dangerous action" semantic color. The `--danger` CSS variable, the `--color-danger` theme mapping, and the `danger` Badge component variant SHALL NOT exist in the codebase.

#### Scenario: No danger token remains in base.css
- **WHEN** `resources/css/base.css` is searched for `--danger` or `--color-danger`
- **THEN** no matches are found

#### Scenario: No danger usage remains in component code
- **WHEN** `resources/js` is searched for `text-danger`, `bg-danger`, `border-danger`, or a `danger` variant/prop value
- **THEN** no matches are found, and the equivalent call sites use `destructive` instead

### Requirement: Dark-mode coverage for semantic status colors
The `success`, `warning`, `info`, and `gray` tokens SHALL each have a distinct value defined in the `.dark` scope of `resources/css/base.css`, separate from their `:root` (light) value.

#### Scenario: Dark overrides exist for all four status tokens
- **WHEN** the `.dark` scope in `resources/css/base.css` is inspected
- **THEN** it defines `--success`, `--warning`, `--info`, and `--gray`, each with a value different from its `:root` counterpart

### Requirement: Design system reference documentation
A file at `docs/design-system.md` SHALL exist and SHALL document: the system/brand tier split with the full list of which variable belongs to which tier, an applicator-facing rebranding walkthrough naming the exact brand-tier variables, and a semantic reference explaining the meaning of each system-tier color token (including that `destructive` is the sole error/danger color).

#### Scenario: Documentation file exists and covers the tier split
- **WHEN** `docs/design-system.md` is read
- **THEN** it lists every brand-tier variable by name and every system-tier category, and states the do/don't rule for each tier

#### Scenario: Documentation explains the destructive/danger consolidation
- **WHEN** `docs/design-system.md`'s semantic color reference section is read
- **THEN** it states that `destructive` is the single canonical name for error/dangerous-action styling and that no separate `danger` token exists
