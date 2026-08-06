## Why

New users invited to a workspace get silently auto-enrolled into their own personal "default" workspace before they ever get a chance to join the workspace they were invited to. This happens because `WorkspaceServiceProvider::registerInertiaSharedData()` calls `ensureHasDefaultWorkspace()` unconditionally on the first authenticated page load, and the invite-accept route requires an existing session that a brand-new invitee cannot have yet. A first pass at fixing this by auto-attaching every pending invitation that shares the registrant's email turned out to introduce its own problem: it silently joins the user to workspaces they never explicitly acted on — a real issue when unrelated organizations happen to invite the same address independently. The design here fixes the original onboarding conflict without trading it for a silent, non-consensual one.

## What Changes

- Replace the `auth`-gated invite acceptance link with a signed, time-limited link (`URL::temporarySignedRoute`) scoped to one specific invitation row, reachable without a prior session.
- When an unauthenticated visitor opens a signed invitation link, the specific invitation id is carried through the session into registration. On `Registered`, only **that one** invitation is auto-accepted — never a blanket match against every pending invitation sharing the same email.
- **BREAKING (behavioral)**: `ensureHasDefaultWorkspace()`'s silent auto-creation of a personal workspace is retired as an entry point for a user's *first* workspace. Any authenticated user with zero accepted `user_workspace` memberships is instead routed to a new onboarding/choice screen: accept one of their pending invitations, or create their own workspace. This applies even to organic (non-invited) signups — they see a create-only form pre-filled with a suggested personalized name.
- Extract a shared `AcceptWorkspaceInvite` action, used both by the click-through accept controller and the session-based post-registration auto-accept path, so accepting an invitation always sets `current_workspace_id` to that invitation's workspace.
- Normalize invitee email to lowercase at invite-creation time.
- Add a "resend invitation" action/endpoint that refreshes an existing pending invite's signed link without requiring the row to be deleted and recreated.
- Remove the global cross-tenant uniqueness constraint on `workspaces.name`.
- **New**: add an `is_default` flag on `user_workspace` marking a user's anchor/home membership. It is used to deterministically resolve `current_workspace_id` when it becomes stale (workspace deleted, user removed from it), replacing today's implicit `workspaces()->first()` ordering. A membership is flagged default on a user's first-ever acceptance/creation, and self-created workspaces always take over as the new default. If the default membership is later removed, the oldest remaining membership is automatically promoted.

## Capabilities

### New Capabilities
(none)

### Modified Capabilities
- `vitamind-workspace-plugin`: invitation acceptance no longer requires an existing session; only the specific invitation referenced by a signed link is ever auto-accepted (never a bulk email match); any user without an accepted workspace membership — invited or not — is routed through an explicit onboarding/choice screen instead of silent default-workspace creation; accepting an invitation updates the user's current workspace; invitations can be resent; workspace names no longer require global uniqueness; a new `is_default` flag on `user_workspace` deterministically anchors self-heal workspace resolution.

## Impact

- **Affected code**: `dev-packages/vitamind-workspace-plugin/src/Actions/Workspaces/InviteToWorkspace.php`, `.../Actions/Workspaces/CreateWorkspace.php`, `.../Concerns/HasWorkspaces.php`, `.../Http/Controllers/Workspace/AcceptWorkspaceInviteController.php`, `.../Http/Controllers/Workspace/WorkspaceUserController.php`, `.../Http/Controllers/Workspace/LeaveWorkspaceController.php`, `.../Http/Middleware/HasWorkspaceMiddleware.php`, `.../Providers/WorkspaceServiceProvider.php`, `.../Mail/WorkspaceInvitation.php`, `.../Models/UserWorkspace.php`.
- **New code**: `AcceptWorkspaceInvite` action, a resend-invitation action/endpoint, a new onboarding/choice-screen controller + Inertia page, a new middleware guarding "no accepted membership" requests.
- **New migration**: `user_workspace` gains an `is_default` boolean column plus a generated column + unique index (MySQL does not support filtered/partial unique indexes, so uniqueness is enforced via a `STORED GENERATED` column that collapses to `NULL` when `is_default` is false, unique-indexed on that column). Includes a data-backfill step for existing rows.
- **No changes** to `vitamind-core` — the `Registered` event this relies on is already fired by `RegisteredUserController::store()`.
- **Existing tests affected**: `tests/Feature/WorkspaceTest.php` and `tests/Feature/WorkspaceUserCleanupTest.php` call `ensureHasDefaultWorkspace()` directly and assert on the literal name `'default'`; both need updating for the retired silent-creation branch and the personalized/suggested naming.
