## ADDED Requirements

### Requirement: Each distributed plugin declares its own frontend dependencies
Each plugin package under `dev-packages/vitamind-*` that ships frontend source SHALL have its own `package.json` declaring, at minimum, its dependency on the shared UI-kit package (the eventual npm-published form of `@vitamind/ui`, see `extract-frontend-ui-kit`) with an explicit version constraint — the frontend equivalent of the `composer.json` `require` entry the same plugin already declares for `vitamind/plugin-sdk`.

#### Scenario: Plugin declares its UI-kit dependency
- **WHEN** `dev-packages/vitamind-workspace-plugin` is inspected
- **THEN** it has a `package.json` with a `dependencies` (or `peerDependencies`, per the implementation decision made when this capability is executed) entry for the UI-kit package and a version constraint
- **AND** that constraint is enforced by standard npm/workspace tooling, not a bespoke VitaminD script

#### Scenario: Version drift is detectable
- **WHEN** the host's installed UI-kit version and a plugin's declared constraint are incompatible
- **THEN** standard dependency-resolution tooling (`npm install`, or the workspace tool chosen at execution time) surfaces the conflict, instead of the mismatch only manifesting as a runtime error

### Requirement: This capability is inactive until an execution trigger is met
This capability's requirements describe the TARGET state once `add-per-plugin-npm-packages` is executed. They SHALL NOT be treated as already implemented. The change SHALL remain unexecuted until one of the trigger conditions documented in its `design.md` (real dependency-version drift causing a bug, a genuine non-monorepo/arms-length consumer, or an explicit project-owner decision) is met.

#### Scenario: Status check before assuming this capability is live
- **WHEN** someone reads this spec and considers relying on per-plugin `package.json` dependency declarations
- **THEN** they first check whether `add-per-plugin-npm-packages` has been moved out of `openspec/changes/` (i.e., archived as implemented)
- **AND** if it has not, they treat every plugin as still compiled together with the host in a single Vite process with no per-plugin dependency manifest, per `plugin-frontend-distribution`
