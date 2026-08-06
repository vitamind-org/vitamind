## 1. Database: default-membership tracking

- [x] 1.1 Create a migration adding `is_default` boolean (default `false`) to `user_workspace`.
- [x] 1.2 In the same migration, add a `STORED GENERATED` column (e.g. `default_owner_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN is_default THEN user_id ELSE NULL END) STORED`) and a unique index on it — MySQL does not support filtered/partial unique indexes, so this is the mechanism that enforces "at most one `is_default = true` row per user".
- [x] 1.3 In the same migration (or a follow-up data migration), backfill existing data: for each user with a resolvable `current_workspace_id`, set `is_default = true` on their matching `user_workspace` row; for users with no resolvable current workspace but at least one membership, set `is_default = true` on their earliest-created membership.
- [x] 1.4 Add `is_default` to `UserWorkspace`'s `$fillable` and `$casts` (`dev-packages/vitamind-workspace-plugin/src/Models/UserWorkspace.php`).

## 2. Signed invitation links

- [x] 2.1 In `InviteToWorkspace::invite()` (`dev-packages/vitamind-workspace-plugin/src/Actions/Workspaces/InviteToWorkspace.php`), normalize the invitee email to lowercase (`Str::lower($input['email'])`) before creating the `UserWorkspace` row.
- [x] 2.2 Still in `InviteToWorkspace::invite()`, generate the invitation URL with `URL::temporarySignedRoute('workspaces.invitations.accept', now()->addDays(7), ['workspace' => $workspace->id, 'invite' => $userWorkspace->id])` and pass it into `WorkspaceInvitation` instead of the plain `route()` call currently built inside the mailable.
- [x] 2.3 Update `WorkspaceInvitation` mailable (`dev-packages/vitamind-workspace-plugin/src/Mail/WorkspaceInvitation.php`) to accept the pre-generated signed URL rather than building the link itself.
- [x] 2.4 In `AcceptWorkspaceInviteController` (`dev-packages/vitamind-workspace-plugin/src/Http/Controllers/Workspace/AcceptWorkspaceInviteController.php`), change the route middleware from `auth` to `signed`, and accept the `invite` id parameter so it resolves the specific `UserWorkspace` row.
- [x] 2.5 In the same controller, branch on `auth()->check()`: if authenticated and the email matches, call `AcceptWorkspaceInvite::accept()` (Section 3) directly; if unauthenticated, store the invitation id in session (e.g. `session(['pending_invite_id' => $invite->id])`) and redirect to `route('register')`.

## 3. Unified accept action

- [x] 3.1 Create `AcceptWorkspaceInvite` action (e.g. `dev-packages/vitamind-workspace-plugin/src/Actions/Workspaces/AcceptWorkspaceInvite.php`) with `accept(UserWorkspace $invite, User $user): void` that: sets `$invite->user_id`, clears `$invite->email`; sets `$user->current_workspace_id = $invite->workspace_id`; and, if the user had zero accepted memberships before this call, flags `$invite->is_default = true`. Save both models.
- [x] 3.2 Update `AcceptWorkspaceInviteController::__invoke()` to call this action instead of its current inline logic.
- [x] 3.3 Add an `Illuminate\Auth\Events\Registered` listener in `WorkspaceServiceProvider::registerEventListeners()` (`dev-packages/vitamind-workspace-plugin/src/Providers/WorkspaceServiceProvider.php`) that reads `session('pending_invite_id')`; if present, loads that `UserWorkspace`, verifies its email matches `$event->user->email`, and calls `AcceptWorkspaceInvite::accept()` for it only. **Do not** query or bulk-attach any other pending invitation matching the user's email.

## 4. Onboarding/choice screen

- [x] 4.1 Add a new middleware (e.g. `EnsureWorkspaceOnboarded`), registered globally for authenticated web routes when the workspace feature is enabled, that redirects to a new onboarding route whenever `auth()->check()` and the user has zero accepted `user_workspace` rows (`UserWorkspace::where('user_id', $user->id)->exists()` is false) — excluding the onboarding route itself and logout.
- [x] 4.2 Add a controller + route (e.g. `GET settings/workspaces/onboarding`, named `workspaces.onboarding`) rendering an Inertia page that lists the user's pending invitations (by email) and a create-workspace form pre-filled with a suggested name (e.g. `"{$user->name}'s Workspace"`).
- [x] 4.3 Add an "accept" action on this page/controller that calls `AcceptWorkspaceInvite::accept()` for the chosen invitation only.
- [x] 4.4 Wire the create-workspace form on this page to the existing `CreateWorkspace::create()` action (Section 6 covers the `is_default` handling inside it).
- [x] 4.5 Remove the auto-creation branch from `ensureHasDefaultWorkspace()` (`dev-packages/vitamind-workspace-plugin/src/Concerns/HasWorkspaces.php`) — it should now only resolve an existing membership (via `is_default`, see Section 5) and never create a new workspace. Update its callers (`WorkspaceServiceProvider::registerInertiaSharedData()`, `HasWorkspaceMiddleware`) accordingly, since the new middleware in 4.1 now handles the zero-membership case earlier in the request lifecycle.

## 5. Default-membership resolution and reassignment

- [x] 5.1 Update `ensureHasDefaultWorkspace()`'s self-heal logic to resolve via `UserWorkspace::where('user_id', $this->id)->where('is_default', true)->first()` instead of `workspaces()->first()`.
- [x] 5.2 In `CreateWorkspace::create()` (`dev-packages/vitamind-workspace-plugin/src/Actions/Workspaces/CreateWorkspace.php`), within a transaction: unset any existing `is_default = true` row for the creating user, then set `is_default = true` on the newly created membership.
- [x] 5.3 In `LeaveWorkspaceController`, after removing the user's membership: if it was flagged `is_default` and other memberships remain, promote the remaining membership with the earliest `created_at` to `is_default = true`.
- [x] 5.4 In `WorkspaceUserController::destroy()`, apply the same reassignment logic as 5.3 when an admin removes a user from their default workspace.
- [x] 5.5 Remove the `unique:workspaces,name` rule from `CreateWorkspace::validate()`.

## 6. Resend and revoke pending invitations

- [x] 6.1 Confirm `WorkspaceUserController::destroy()` already correctly revokes a pending (email-only) invitation with no code change beyond 5.4; add a test asserting a previously valid signed link 404s at `AcceptWorkspaceInviteController` after the row is deleted.
- [x] 6.2 Add a `ResendWorkspaceInvitation` action that loads an existing pending `UserWorkspace` row by id, generates a fresh `temporarySignedRoute` link, and re-sends `WorkspaceInvitation`.
- [x] 6.3 Add a route + controller method (e.g. `POST settings/workspaces/{workspace}/users/{id}/resend`) wired to the action in 6.2, authorized the same way as `store()`/`destroy()` (`$this->authorize('update', $workspace)`).

## 7. Test updates

- [x] 7.1 Update `tests/Feature/WorkspaceTest.php` and `tests/Feature/WorkspaceUserCleanupTest.php` for the retired silent-creation branch of `ensureHasDefaultWorkspace()` and the removal of the literal `'default'` name.
- [x] 7.2 Add a feature test: a user with two pending invitations clicks the signed link for one, registers, and ends up attached only to that one workspace — the other remains pending.
- [x] 7.3 Add a feature test: a user with a pending invitation registers directly at `/register` without visiting any invitation link, is not auto-attached to anything, and is routed to the onboarding/choice screen.
- [x] 7.4 Add a feature test: a user with no pending invitations registers and is routed to the onboarding/choice screen showing only the create-workspace option with a pre-filled suggested name.
- [x] 7.5 Add a feature test: accepting an invitation from the onboarding/choice screen attaches the user only to the chosen workspace, ignoring any other pending invitations.
- [x] 7.6 Add a feature test: an unauthenticated visitor opening a signed invitation link is not blocked by an auth error; an expired or tampered signed link is rejected.
- [x] 7.7 Add a feature test: an already-registered user with an active workspace accepts an invitation via signed link — `current_workspace_id` switches and they are not routed through the onboarding screen.
- [x] 7.8 Add a feature test for `is_default`: first accepted membership is flagged default; creating a second self-owned workspace supersedes the previous default; leaving/removal from the default workspace promotes the oldest remaining membership; a user can never have two `is_default = true` rows (assert the DB constraint holds).

## 9. Revision: slug-based name uniqueness (post-implementation)

Case-only name variants (`Acme` vs `ACME`) were exploitable to spoof an existing workspace once Section 5.5 dropped uniqueness entirely — see Decision 8 (revised) in `design.md`.

- [x] 9.1 Add a migration adding `slug` to `workspaces`, backfilling it for existing rows (`Str::slug(name)`, falling back to `'workspace'` on an empty result, with a `-2`, `-3`, ... suffix on collision in ascending `id` order), then adding a unique index on `slug`.
- [x] 9.2 Update `CreateWorkspace::validate()`: allow `name` to contain letters (any case), numbers, dashes, and spaces (`regex:/^[A-Za-z0-9- ]+$/`); derive `slug` from `name` and validate it as `required` (rejects an all-space/all-dash name) and unique against `workspaces.slug`. Set `$workspace->slug` before save.
- [x] 9.3 Apply the same validation shape to `UpdateWorkspace::validate()`/`update()`, replacing its existing raw-`name` `Rule::unique` with the slug-based one, and drop the forced `strtolower()` in `update()`.
- [x] 9.4 Update `WorkspaceOnboardingController::index()`'s suggested name: stop forcing lowercase, keep the user's original casing, and sanitize to the allowed character set so it stays a valid, submittable default even when the user's real name contains characters outside it.
- [x] 9.5 Add feature tests: disallowed characters rejected; all-space/all-dash name rejected; two names differing only by case/whitespace collide and the second is rejected; names normalizing to different slugs both succeed; suggested onboarding name preserves casing.
