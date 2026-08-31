## ADDED Requirements

### Requirement: Plugins can check real workspace membership via a shared primitive

`vitamind/plugin-sdk` SHALL provide a workspace-membership check, `VitaminD\PluginSdk\Support\WorkspaceMembership::check(?Authenticatable $user, int|string $workspaceId): bool`, for plugins that must authorize access to a resource scoped to an arbitrary workspace — as opposed to `BelongsToWorkspace`, which only scopes a model to the acting user's own current workspace. The check SHALL reference only the `vitamin-d.features.workspaces` config flag and the `user_workspace` table's `user_id`/`workspace_id` columns, and SHALL NOT import anything from `vitamind/workspace-plugin`.

#### Scenario: Member of the target workspace is authorized
- **WHEN** `WorkspaceMembership::check($user, $workspaceId)` is called for a user who has a `user_workspace` row for `$workspaceId`
- **THEN** it returns `true`

#### Scenario: Non-member of the target workspace is denied
- **WHEN** `WorkspaceMembership::check($user, $workspaceId)` is called for a user with no `user_workspace` row for `$workspaceId`
- **THEN** it returns `false`

#### Scenario: A workspace matching the user's current workspace but with no membership row is still denied
- **WHEN** `$workspaceId` equals the user's `current_workspace_id` but no corresponding `user_workspace` row exists
- **THEN** `WorkspaceMembership::check()` returns `false`
- **AND** this demonstrates the check verifies real membership, not merely equality with `current_workspace_id`

#### Scenario: Disabled workspaces feature denies by default
- **WHEN** `vitamin-d.features.workspaces` is disabled
- **THEN** `WorkspaceMembership::check()` returns `false` regardless of any existing membership row

#### Scenario: No authenticated user denies by default
- **WHEN** `WorkspaceMembership::check(null, $workspaceId)` is called
- **THEN** it returns `false`

#### Scenario: Non-canonical workspace id is rejected
- **WHEN** `$workspaceId` is not a canonical positive-integer string (e.g. `"1x"`, `"01"`, `"1.0"`, `"-1"`, `"0"`, `""`)
- **THEN** `WorkspaceMembership::check()` returns `false` without matching it to a numerically-equivalent real workspace id

#### Scenario: The SDK requires no workspace plugin dependency
- **WHEN** `vitamind/plugin-sdk`'s dependencies are examined after this primitive is added
- **THEN** `vitamind/workspace-plugin` is still not among them

### Requirement: `vitamind/realtime-plugin`'s channel authorization delegates to the shared primitive without changing behavior

`VitaminD\Plugins\Realtime\Support\WorkspaceChannelAuthorization::check()` SHALL delegate to `VitaminD\PluginSdk\Support\WorkspaceMembership::check()` instead of performing its own inline membership query, while producing identical results for identical inputs to its pre-refactor implementation.

#### Scenario: Existing channel authorization test suite passes unchanged
- **WHEN** `WorkspaceChannelAuthorizationTest`'s existing cases (member authorized, non-member denied, feature disabled, no user, non-canonical ids) are run against the refactored `WorkspaceChannelAuthorization`
- **THEN** every case produces the same pass/fail result as before the refactor, with no test modifications required
