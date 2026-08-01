## 0. Gate Check (blocking — do not proceed until satisfied)

- [ ] 0.1 Confirm `phase1-standalone-boilerplate` is closed
- [ ] 0.2 Confirm `stabilize-vitamind-packages` gate passed (≥2 of 3: BukuWarga, LembarUji, UangKas extended successfully without breaking changes)

---

## 1. Publishing & Organization Setup

*(dipindah dari `phase1-standalone-boilerplate` §14; item yang sudah selesai di sana dibawa sebagai catatan, tidak perlu diulang)*

- [ ] 1.1 Prepare `vitamind/core` package for Packagist publishing (finalize versioning, composer.json metadata)
- [ ] 1.2 Prepare `vitamind/workspace-plugin` package for Packagist publishing (finalize versioning, composer.json metadata)
- [x] 1.3 README.md in each package with clear usage instructions (done in phase1-standalone-boilerplate 14.3)
- [x] 1.4 LICENSE file in both packages (done in phase1-standalone-boilerplate 14.4)
- [x] 1.5 GitHub repos in vitamind-org created for core and workspace plugin, used as mirror during stabilization (done in phase1-standalone-boilerplate 14.5/14.6)
- [ ] 1.6 Register `vitamind/core` on Packagist
- [ ] 1.7 Register `vitamind/workspace-plugin` on Packagist
- [ ] 1.8 Update vitamind-org README/docs to list all available packages

---

## 2. Release & Verification

*(dipindah dari `phase1-standalone-boilerplate` §15.3/15.4)*

- [ ] 2.1 Verify all GitHub organization repositories are properly structured for a real release (not just mirror)
- [ ] 2.2 Create GitHub release tags for core and workspace plugin v0.1.0
- [ ] 2.3 Verify fresh installation works purely via Packagist: `composer create-project laravel/laravel && composer require vitamind/core` (no path repository)
- [ ] 2.4 Verify workspace plugin installable the same way: `composer require vitamind/workspace-plugin`

---

## 3. Closure

- [ ] 3.1 Update CHANGELOG.md noting packages are now publicly published
- [ ] 3.2 Mark `publish-vitamind-packages` as complete in OpenSpec
