## Why

Any plugin that scopes its own data to the current workspace already has `VitaminD\PluginSdk\Concerns\BelongsToWorkspace`. But a plugin that needs to authorize access to *another user's* workspace-scoped resource — not just filter its own records — needs a real membership check (is the acting user actually a member of workspace W, not merely "does W match my currently active workspace"). `vitamind/realtime-plugin`'s `WorkspaceChannelAuthorization` already implements exactly this, inline, because a channel subscriber can request any workspace's channel, not just their active one. `vitamind/archive-plugin` (in development, see `add-archive-plugin`) needs the identical check for its `workspace`-visibility file/folder tier. Two independent inline copies of the same convention-based query is the point at which it belongs in `plugin-sdk`, next to `BelongsToWorkspace`, instead.

## What Changes

- New shared primitive in `vitamind/plugin-sdk`: a dependency-free workspace-membership check (e.g. `VitaminD\PluginSdk\Support\WorkspaceMembership::check($user, $workspaceId)`), following the same convention-based approach as `BelongsToWorkspace` — it knows only the `vitamin-d.features.workspaces` config flag and the `user_workspace` pivot table's `user_id`/`workspace_id` columns, and never imports anything from `vitamind/workspace-plugin`. Returns `false` (never grants) when the workspaces feature is disabled, when there's no authenticated user, or when the workspace id isn't a genuine positive integer.
- `vitamind/plugin-sdk` continues to not `require` `vitamind/workspace-plugin`.
- Refactor `vitamind/realtime-plugin`'s `WorkspaceChannelAuthorization::check()` to delegate to the new `plugin-sdk` primitive instead of its own inline `DB::table('user_workspace')` query. **No behavior change** — same inputs produce the same authorization result; this is purely a consolidation of duplicated logic into its shared home.

## Capabilities

### New Capabilities
_(none — this extends the existing `plugin-workspace-scoping` capability)_

### Modified Capabilities
- `plugin-workspace-scoping`: adds a requirement for a shared, dependency-free workspace-membership check primitive in `vitamind/plugin-sdk`, companion to the existing `BelongsToWorkspace` scoping trait, for plugins that must authorize access to another workspace's resource rather than merely scope their own data to the current one.

## Impact

- Modified: `dev-packages/vitamind-plugin-sdk/src/` — new `Support/WorkspaceMembership.php` (or equivalent) alongside `Concerns/BelongsToWorkspace.php`, plus its unit tests.
- Modified: `dev-packages/vitamind-realtime-plugin/src/Support/WorkspaceChannelAuthorization.php` — delegates to the new primitive; its existing tests (`tests/Unit/WorkspaceChannelAuthorizationTest.php`, `tests/Feature/ChannelAuthorizationTest.php`) must keep passing unchanged, since behavior is identical.
- Unblocks: `add-archive-plugin`'s `workspace`-visibility authorization, which consumes this primitive directly instead of duplicating the check.
