## 1. `vitamind-plugin-sdk`: extend `RegisterPage`

- [x] 1.1 Add `route(string $name, array $params = []): self` and `href(string $url): self` to `RegisterPage`, storing the link source internally (route name + params, or raw URL).
- [x] 1.2 In `register()`, throw `InvalidArgumentException` if both `tabs()` and `route()`/`href()` were called on the same instance.
- [x] 1.3 In `toArray()`, add an `href` key: `route('plugins.page', $this->key)` when in tabs mode, the resolved `route($name, $params)` or raw URL when in custom-link mode.
- [x] 1.4 Update `dev-packages/vitamind-plugin-sdk/tests/RegisterPageTest.php`: cover `route()` mode, `href()` mode, the tabs-mode `href` staying `plugins.page`-shaped, and the both-modes-set exception.
- [x] 1.5 Add `description(string $description): self` to `RegisterPage`, storing a nullable internal `$description` (default `null`).
- [x] 1.6 In `toArray()`, add a `description` key holding that value (`null` when never set).
- [x] 1.7 Update `RegisterPageTest.php`: cover `description()` setting the field, and it defaulting to `null` when unset.
- [x] 1.8 Add `placement(string $target): self` to `RegisterPage`, validating against `['main', 'admin', 'settings']` and throwing `InvalidArgumentException` otherwise (validate in `placement()` itself, not deferred to `register()`).
- [x] 1.9 In `toArray()`, add a `placement` key resolved as `$this->placement ?? ($this->adminOnly ? 'admin' : 'main')`.
- [x] 1.10 Update `RegisterPageTest.php`: cover explicit `placement('settings')`, the default resolution for both `adminOnly(false)` and `adminOnly(true)` (matching current implicit behavior), and the invalid-value exception.

## 2. `vitamind-core`: carry the resolved link through Inertia

- [x] 2.1 Confirm `HandleInertiaRequests.php:47`'s `pluginPages` mapping needs no change beyond what `RegisterPage::toArray()` now returns (it already spreads `toArray()`); add a feature/unit test asserting a custom-link entry's `href` reaches the `pluginPages` prop unchanged.

## 3. Boilerplate: generic main-sidebar consumption

- [x] 3.1 In `resources/js/components/app-sidebar.tsx`, replace the `!p.admin_only` filter + `href: route('plugins.page', p.key)` with a filter on `p.placement === 'main'` and `href: p.href` for each `pluginPages` entry.
- [x] 3.2 Update the `SharedData`/plugin-page TypeScript type (wherever `pluginPages` is typed, e.g. `resources/js/types/index.d.ts`) to include `href: string`, `description?: string`, and `placement: 'main' | 'admin' | 'settings'`.
- [x] 3.3 In `resources/js/pages/plugins/dynamic-page.tsx` (around line 261), change the `Heading`'s `description` prop to `` page.description || `Manage plugin database records for ${page.title}.` ``.
- [x] 3.4 Run `npm run types` and manually verify: an existing tabs-mode entry (Todo Plugin) still navigates to the same `plugins.page` URL and shows the same default heading description as before (since it doesn't call `description()`/`placement()`). (`npm run types` clean; manual browser verification deferred to task 6.3.)

## 4. Boilerplate: Admin second-nav fix + Settings second-nav (new)

- [x] 4.1 In `resources/js/layouts/admin/layout.tsx`, replace the `p.admin_only` filter + hardcoded `route('plugins.page', p.key)` with a filter on `p.placement === 'admin'` and `href: p.href`.
- [x] 4.2 In `resources/js/layouts/settings/layout.tsx`, add a `pluginPages` read (`usePage<SharedData>().props.pluginPages || []`) and append an item per entry where `p.placement === 'settings'`, using `p.href`/`p.title`/icon resolution consistent with the other two layouts (extract the shared `getIconComponent` helper if convenient, otherwise duplicate it as the other two layouts already do independently).
- [x] 4.3 Manually verify: an admin-only `tabs()`-mode entry (if any test plugin registers one) still appears in the Admin second-nav and links correctly; the Settings second-nav's existing Profile/Workspaces/API Keys items are unaffected when no plugin targets `placement('settings')`. (Verified via task 6.4's throwaway HTTP checks.)

## 5. `vitamind-archive-plugin`: register the sidebar entry

- [x] 5.1 In `ArchiveServiceProvider::boot()`, call `RegisterPage::make('archive')->title('Archive')->icon('archive')->route('archive.index')->adminOnly(false)->register()` (placement left at its default-resolved `'main'`, matching where "Archive" needs to appear).
- [x] 5.2 Add/extend a feature test asserting the "Archive" entry is present in `pluginPages` with `href` resolving to the `archive.index` URL and `placement` resolving to `'main'`. (Fixed by restoring `tests/TestCase.php`'s pre-flush snapshot for `tabs()`-less pages only — see task 6.1's note.)

## 6. Verification

- [x] 6.1 Run the full PHP test suite (`vendor/bin/pest` or project equivalent) covering `vitamind-plugin-sdk`, `vitamind-core`, and `vitamind-archive-plugin`. (179/179 passing, including with `--order-by=random`. Also fixed an unrelated pre-existing bug this surfaced: `tests/TestCase.php`'s `RegisterPage::flush()` permanently erased any page registered by an "always-on" Composer-package provider like `ArchiveServiceProvider` — see the file's updated docblock. Fix restores only `tabs()`-less snapshot entries, so DB-bound `tabs()` closures like `App\Plugins\MockProduct\Plugin`'s still only flow through the existing `RefreshDatabase`-gated `BootPlugins` re-run.)
- [x] 6.2 Run `vendor/bin/pint` across touched PHP files. (`vendor/bin/pint --dirty` — auto-fixed import ordering/spacing in 4 files; full suite re-run green after.)
- [x] 6.3 Manually load the app, confirm "Archive" appears in the main sidebar and navigates to the plugin's own page, and confirm "Todo Plugin" (or another `tabs()`-based entry, if installed) still works unchanged in both the main sidebar and (if admin-only) the Admin second-nav. (No interactive browser in this environment — verified at the HTTP/data layer instead: `ArchiveMenuRegistrationTest` confirms Archive's `pluginPages` entry end-to-end over a real request/response cycle, and `npm run build` + `npm run types` confirm the sidebar/layout components compile clean against the new prop shape. Full visual confirmation is still recommended in a browser before shipping.)
- [x] 6.4 Manually register a throwaway test page with `->placement('settings')` (e.g. in a test/dev plugin) and confirm it renders in the Settings second-nav; remove the throwaway registration afterward. (Done via a temporary `tests/Feature/ThrowawayPlacementVerificationTest.php` — 3 requests confirmed `main`/`admin`/`settings` placements each reach their respective layout's rendered response; file deleted after passing.)

## 7. Documentation

- [x] 7.1 Create `docs/plugin-development/menu-registration.md` documenting the full `RegisterPage` menu-registration API — `tabs()`, `route()`/`href()`, `description()`, `placement()`, `adminOnly()` — written for two audiences at once: a developer skimming for the method they need, and an AI coding agent that needs the file to be self-sufficient (no chasing definitions across the codebase) when asked to add a plugin's sidebar entry.
- [x] 7.2 Cover, with runnable code examples: registering a `tabs()`-based CRUD page (mirroring `vitamind-todo-plugin`), registering a custom-route page (mirroring `vitamind-archive-plugin`'s `->route('archive.index')`), the three `placement()` destinations and their default-resolution rule from `adminOnly()`, and the explicit rule that plugins must never edit `app-sidebar.tsx` / `admin/layout.tsx` / `settings/layout.tsx` directly — this file is the sanctioned alternative.
- [x] 7.3 Cross-link it from `docs/local-plugins.md` (which currently has no mention of sidebar/menu registration at all), and from this change's own `ArchiveServiceProvider::boot()` registration call as an inline reference, matching the existing style of that file's other doc comments.
- [x] 7.4 Keep the doc in sync with whatever the final shipped API looks like — write it after tasks 1-6 land, not before, so it documents actual behavior rather than the plan. (Written last, after all code/tests landed; includes the `tests/TestCase.php` always-on-provider caveat discovered during implementation.)
