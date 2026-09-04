## ADDED Requirements

### Requirement: Plugin UI primitives are resolved via a neutral shared alias
Distributed plugin frontend components SHALL NOT import React UI primitives (e.g. `Button`, `Dialog`, `Popover`, `Command`) or the `cn()` class-merge utility through the host's own `@/*` alias. They SHALL import them through a neutral alias, `@vitamind/ui/*`, that resolves to the same physical location regardless of whether the importing file belongs to the host or to a plugin.

#### Scenario: Plugin component imports a UI primitive
- **WHEN** a component under `dev-packages/vitamind-{plugin}/resources/js/**` needs `Button`
- **THEN** it imports it as `import { Button } from '@vitamind/ui/button'`
- **AND** it does not import from `@/components/ui/button`

#### Scenario: Host code imports the same primitive
- **WHEN** host code under `resources/js/**` (outside `dev-packages/`) needs the same `Button`
- **THEN** it also imports it as `@vitamind/ui/button`, resolving to the identical physical file the plugin resolves to
- **AND** no host code continues importing UI primitives via `@/components/ui/*`

### Requirement: `@vitamind/ui` is an alias, not an independently distributed package
`@vitamind/ui/*` SHALL be implemented as a Vite resolve alias and a matching `tsconfig.json` `paths` entry pointing at the existing physical location of the UI primitives (`resources/js/components/ui/`) and `cn()` (`resources/js/lib/utils.ts`). It SHALL NOT require a separate `package.json`, independent versioning, or a separate build/watch process — plugin and host code continue compiling together in the same Vite process as established by `plugin-frontend-distribution`'s existing alias/glob mechanisms.

#### Scenario: No physical file relocation
- **WHEN** `@vitamind/ui/*` is introduced
- **THEN** the files under `resources/js/components/ui/` and `resources/js/lib/utils.ts` remain at their current physical paths
- **AND** only the alias used to reach them changes

#### Scenario: Single Vite compile is preserved
- **WHEN** the host runs its normal `npm run dev` or `npm run build`
- **THEN** both host and plugin code resolving `@vitamind/ui/*` are compiled in that same single process, with no additional watch/build step introduced
