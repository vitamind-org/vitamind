## Context

`VitaminD\PluginSdk\Concerns\BelongsToWorkspace` already gives plugins a convention-based way to scope *their own* data to the acting user's current workspace, without depending on `vitamind/workspace-plugin`. It deliberately trusts the user's `current_workspace_id` attribute.

`vitamind/realtime-plugin`'s `Support\WorkspaceChannelAuthorization::check()` needs a different check: whether the acting user is genuinely a member of an *arbitrary* workspace id (the one named in a requested broadcast channel) — `current_workspace_id` isn't a valid proxy here, since a subscriber can request a channel for any workspace, not just their active one. It implements this today as an inline `DB::table('user_workspace')` query, following the exact same "convention over dependency" approach as `BelongsToWorkspace` (same config flag, same pivot table, no import from `vitamind/workspace-plugin`).

`vitamind/archive-plugin` (proposed separately, `add-archive-plugin`) needs the identical check for its `workspace`-visibility tier: a file's `workspace_id` may not be the viewer's current workspace, so authorizing that view requires the same real-membership question. Two independent copies of the same convention is the trigger to promote it into `plugin-sdk`, alongside `BelongsToWorkspace`, as this is now needed by more than one consumer.

## Goals / Non-Goals

**Goals:**
- Provide one shared, dependency-free primitive in `vitamind/plugin-sdk` answering "is user U a member of workspace W", usable by any plugin without requiring `vitamind/workspace-plugin`.
- Preserve `WorkspaceChannelAuthorization`'s existing external behavior and validation rules exactly (canonical positive-integer workspace id, feature-flag gate, no-user gate) — this is a refactor, not a behavior change.
- Unblock `add-archive-plugin`'s `workspace`-visibility authorization to consume this primitive directly instead of duplicating it a third time.

**Non-Goals:**
- Not building a general-purpose "workspace roles/permissions" system — this is a plain membership existence check, same scope as today's inline query.
- Not changing `BelongsToWorkspace`'s behavior or touching its file.
- Not adding a dependency from `vitamind/plugin-sdk` to `vitamind/workspace-plugin` — the new primitive stays convention-based, exactly like `BelongsToWorkspace`.
- Not changing `vitamind/realtime-plugin`'s public API or channel-authorization semantics as observed by its callers.

## Decisions

**D1: New class `VitaminD\PluginSdk\Support\WorkspaceMembership` with a static `check(?Authenticatable $user, int|string $workspaceId): bool` method.**
Mirrors `WorkspaceChannelAuthorization::check()`'s existing signature and validation order exactly, so the refactor in realtime-plugin is a body swap, not a call-site rewrite:
1. Reject any `$workspaceId` that isn't a canonical positive-integer string (`^[1-9][0-9]*$` and round-trips through `(int)` cast) — guards against `"1x"`, `"01"`, `"1.0"` silently coercing to a different id than intended.
2. Return `false` if `vitamin-d.features.workspaces` is disabled.
3. Return `false` if `$user` is `null`.
4. Otherwise, `DB::table('user_workspace')->where('user_id', $user->getAuthIdentifier())->where('workspace_id', (int) $workspaceId)->exists()`.

Placed under `Support/` (a new namespace segment in plugin-sdk) rather than `Concerns/`, since it's a static helper, not a trait meant to be mixed into a model — different shape from `BelongsToWorkspace`, so it doesn't belong in the same folder.

*Alternative considered*: add this as a second method on `BelongsToWorkspace` itself. Rejected — `BelongsToWorkspace` is a model trait (`static::` context, global scopes, model lifecycle hooks); a stateless authorization check called from a policy or channel callback has no model context to attach to. Keeping them as separate, purpose-named primitives under `plugin-sdk` is clearer than overloading a trait with an unrelated static method.

**D2: `WorkspaceChannelAuthorization::check()` becomes a thin delegation to `WorkspaceMembership::check()`.**
Its own validation logic and inline query are deleted; the method body becomes a one-line delegation. Its class and public signature stay — `vitamind/realtime-plugin` callers (`Broadcast::channel()` closures) are unaffected. Existing tests in `WorkspaceChannelAuthorizationTest` continue to exercise the same input/output contract and must keep passing unmodified, proving the refactor is behavior-preserving.

*Alternative considered*: delete `WorkspaceChannelAuthorization` entirely and have realtime-plugin's channel closures call `WorkspaceMembership::check()` directly. Rejected for this change — that's a wider call-site change across `RealtimeServiceProvider`/consumer code for no functional gain, and keeping the class as a thin wrapper is a strictly smaller, safer diff. Removing the wrapper later, if it ever proves to be pure indirection, is a separate decision.

**D3: `plugin-sdk` still does not require `vitamind/workspace-plugin`.**
`WorkspaceMembership` references only `Config` (`vitamin-d.features.workspaces`) and the `user_workspace` table — same convention `BelongsToWorkspace` and the current inline `WorkspaceChannelAuthorization` already rely on. No new Composer dependency is introduced.

## Risks / Trade-offs

- [Behavior drift during refactor] → Mitigation: port the exact validation logic and regex verbatim (D1); run `WorkspaceChannelAuthorizationTest` unmodified against the refactored class before and after to confirm identical pass/fail results.
- [`user_workspace` table/column names change in a future workspace-plugin migration, silently breaking two convention-based consumers instead of one] → Mitigation: this was already a latent risk with two inline copies; consolidating to one primitive actually reduces the blast radius of such a change to a single file.
- [New `Support/` namespace segment in plugin-sdk with only one class feels like premature structure] → Accepted trade-off: mirrors the existing `Concerns/`/`DTOs/`/`Interfaces/` per-purpose folder convention already used in this package (see `dev-packages/vitamind-plugin-sdk/src/`), so it's consistent rather than novel.

## Migration Plan

1. Add `VitaminD\PluginSdk\Support\WorkspaceMembership` with unit tests covering the same cases as `WorkspaceChannelAuthorizationTest` (member authorized, non-member denied, feature disabled, no user, non-canonical ids).
2. Refactor `WorkspaceChannelAuthorization::check()` to delegate; keep its existing test suite passing unchanged.
3. No database migration, no config change, no consumer-facing API change — safe to land independently and roll back by reverting the delegation (D2) without touching data.

## Open Questions

- None outstanding — `add-archive-plugin`'s consumption of this primitive is described in that change's own design.
