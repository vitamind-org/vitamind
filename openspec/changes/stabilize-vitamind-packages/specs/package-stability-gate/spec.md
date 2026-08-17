## ADDED Requirements

### Requirement: Publishing gate defined by real-world dogfooding

`vitamind/core` and `vitamind/workspace-plugin` SHALL NOT be considered stable enough for Packagist publishing until at least 2 of the following 4 consumer projects have successfully extended this boilerplate via a `dev-packages/` path repository, with no breaking change required to either package: BukuWarga, LembarUji, UangKas, wakuwaku.

#### Scenario: Fewer than 2 projects have integrated successfully
- **WHEN** fewer than 2 of the 4 named consumer projects have completed integration without requiring a breaking change to `vitamind/core` or `vitamind/workspace-plugin`
- **THEN** the gate is not satisfied
- **AND** `publish-vitamind-packages` SHALL NOT be executed

#### Scenario: At least 2 projects have integrated successfully
- **WHEN** at least 2 of the 4 named consumer projects have completed integration — each configuring a `dev-packages/`-pointing path repository in its own `composer.json` and running `composer require vitamind/core` (plus `vitamind/workspace-plugin` where applicable) against it, not against Packagist — without requiring a breaking change
- **AND** at least one of those integrations exercises `vitamind/workspace-plugin`, not `vitamind/core` alone
- **THEN** the gate is satisfied
- **AND** `publish-vitamind-packages` is unblocked for execution

#### Scenario: A consumer project surfaces a breaking-change requirement
- **WHEN** a consumer project can only integrate successfully by requiring a breaking change to `vitamind/core` or `vitamind/workspace-plugin`
- **THEN** that project does not count toward the gate
- **AND** the breaking change is evaluated and, if adopted, implemented as its own change against `vitamind/core` or `vitamind/workspace-plugin` before that project's integration is retried
