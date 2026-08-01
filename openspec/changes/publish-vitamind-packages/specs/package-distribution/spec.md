## ADDED Requirements

### Requirement: Stability gate must pass before Packagist registration

`vitamind/core` and `vitamind/workspace-plugin` SHALL NOT be registered on Packagist until the stability gate defined in `stabilize-vitamind-packages` (capability `package-stability-gate`) is satisfied: at least 2 of 3 dogfooding projects (BukuWarga, LembarUji, UangKas) have successfully extended the boilerplate via `dev-packages/` path repositories without requiring a breaking change to either package.

#### Scenario: Gate not yet satisfied
- **WHEN** fewer than 2 of the 3 dogfooding projects have completed integration without a breaking change
- **THEN** `vitamind-org/vitamind-core` and `vitamind-org/vitamind-workspace-plugin` on GitHub remain mirror-only (code pushed, no version tags, no GitHub releases)
- **AND** neither package is registered on Packagist

#### Scenario: Gate satisfied
- **WHEN** at least 2 of the 3 dogfooding projects have successfully extended the boilerplate without a breaking change
- **THEN** this change proceeds to prepare and register both packages on Packagist

### Requirement: vitamind/core and vitamind/workspace-plugin are installable via Packagist

Once the stability gate passes, `vitamind/core` and `vitamind/workspace-plugin` SHALL be installable in any fresh Laravel project purely via Packagist, without requiring a local path repository.

#### Scenario: Fresh install via Packagist only
- **WHEN** a developer runs `composer create-project laravel/laravel` followed by `composer require vitamind/core`, with no path repository configured
- **THEN** the package resolves and installs from Packagist
- **AND** the installed package behaves identically to the path-repository-based installation already verified in `phase1-standalone-boilerplate` (task 12.9)

#### Scenario: Workspace plugin installable the same way
- **WHEN** a developer runs `composer require vitamind/workspace-plugin` in a project that already has `vitamind/core` installed via Packagist
- **THEN** the workspace plugin resolves and installs from Packagist
- **AND** functions identically to the path-repository-based installation
