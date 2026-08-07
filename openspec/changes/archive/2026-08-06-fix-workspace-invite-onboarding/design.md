## Context

The workspace plugin (`dev-packages/vitamind-workspace-plugin`) is a feature-flagged, optional package layered on top of `vitamind-core` via Composer auto-discovery; core has no knowledge of it. Two independent mechanisms currently touch workspace membership for a user, and they race:

1. **Lazy default-provisioning**: `WorkspaceServiceProvider::registerInertiaSharedData()` calls `$user->ensureHasDefaultWorkspace()` (`Concerns/HasWorkspaces.php`) on *every* Inertia response for an authenticated user without a resolvable `currentWorkspace`. It creates a workspace named `'default'` and makes the user its owner if `workspaces()->first()` is empty.
2. **Invite acceptance**: `InviteToWorkspace::invite()` creates a `user_workspace` row keyed by `email` (`user_id` NULL) and mails a link to `AcceptWorkspaceInviteController`, gated by `#[Middleware(['auth'])]`.

A brand-new invitee cannot authenticate without registering first, and `RegisteredUserController::store()` redirects straight to `route('dashboard')` rather than `redirect()->intended()`. So the first authenticated page load after registration hits mechanism (1) before the user has any chance to go through mechanism (2).

An earlier iteration of this design fixed that by resolving *every* pending invitation matching the registrant's email at registration time. That introduced a new problem: it silently attaches the user to any workspace that happens to share their email address, without regard to whether they ever acted on that specific invitation — a real consent gap when unrelated organizations invite the same address independently, or when a user has multiple pending invites and only intended to act on one of them.

The codebase already has established precedent for the two building blocks this design leans on:
- **Signed, expiring URLs**: `URL::temporarySignedRoute` + `signed` middleware, already used for email verification (`routes/auth.php:43`, `tests/Feature/Auth/EmailVerificationTest.php`).
- **Plugin-level event listeners on core-fired events**: `WorkspaceServiceProvider::registerEventListeners()` already listens for `VitaminD\Core\Events\UserRemoving` to clean up `user_workspace` rows when core removes a user, without core knowing about the plugin.

The application's database driver is MySQL (`.env`: `DB_CONNECTION=mysql`), which matters for how "at most one default membership per user" is enforced (see Decision 5).

## Goals / Non-Goals

**Goals:**
- An invited user ends up a member of the workspace they were invited to, but *only* the specific invitation they actually acted on (clicked the link for) is ever auto-accepted — never a blanket match on email.
- Every user, invited or not, explicitly chooses their first workspace — accept a pending invitation, or create their own — rather than one being silently provisioned for them.
- Invitation links are non-forgeable and expire.
- Admins can revoke a pending invitation and separately can resend one (refreshed link).
- Workspace names stop being forced into a meaningless global-uniqueness constraint.
- A user's "home"/anchor workspace membership is tracked explicitly, so resolving a stale `current_workspace_id` is deterministic rather than relying on implicit row ordering.

**Non-Goals:**
- No single-active-link enforcement on resend — multiple simultaneously valid links for the same invitation are an accepted trade-off.
- No changes anywhere in `vitamind-core` (registration controller, Fortify actions, auth routes). This design relies only on the `Registered` event core already fires from `RegisteredUserController::store()`.
- No automatic re-invitation of the sender or notification-based nudges for users sitting on the onboarding/choice screen — they simply see it again on their next request until they act.
- No scheduled cleanup job for expired, never-accepted invitations — left for manual admin action (revoke/resend).

## Decisions

### 1. Signed routes instead of a hand-rolled invite token

`AcceptWorkspaceInviteController`'s route switches from `auth` to `signed` middleware. `InviteToWorkspace::invite()` generates the mail link via `URL::temporarySignedRoute('workspaces.invitations.accept', now()->addDays(7), ['workspace' => $workspace->id, 'invite' => $userWorkspace->id])`.

*Alternative considered*: a hand-computed hash of `workspace_id + invited_at`. Rejected — without an HMAC secret it's guessable, and without the specific invitation row id in the signed payload, two invites issued to the same workspace around the same time would collide. `temporarySignedRoute` gives HMAC-signed (via `APP_KEY`), expiring, tamper-proof URLs for free, matching the existing email-verification pattern.

### 2. Only the specifically-referenced invitation is ever auto-accepted

When an unauthenticated visitor opens a signed invitation link, `AcceptWorkspaceInviteController` stores that invitation's id in the session (e.g. `session(['pending_invite_id' => $invite->id])`) before redirecting to `/register`. The `Registered` event listener in `WorkspaceServiceProvider` reads that session value (if present), loads the referenced `UserWorkspace`, verifies its email still matches the newly registered user's email, and accepts **only that row** via the action in Decision 3.

*Alternative rejected*: resolving all pending `UserWorkspace` rows matching the registrant's email in bulk. This was the first iteration of this design and is explicitly walked back — it attaches a user to workspaces they never took any action on, which is a real consent problem, not just an edge case.

If no `pending_invite_id` is present in session (the user registered without ever opening an invitation link), nothing is auto-accepted here — this case is handled entirely by Decision 4.

### 3. Unified `AcceptWorkspaceInvite` action

Extract `AcceptWorkspaceInvite::accept(UserWorkspace $invite, User $user)`:
1. Set `$invite->user_id`, clear `$invite->email`, save (today's inline logic in `AcceptWorkspaceInviteController`).
2. Set `$user->current_workspace_id = $invite->workspace_id`, save.
3. If this is the user's first-ever accepted membership (`$user->workspaces()->count() === 0` prior to this call), flag `$invite->is_default = true` (see Decision 5).

Called from `AcceptWorkspaceInviteController::__invoke()` (already-authenticated click-through), the `Registered` listener (Decision 2's single-invite auto-accept), and the onboarding choice screen's "accept" action (Decision 4).

Because every acceptance is now a single, discrete, user-driven action (a click or an explicit choice), `current_workspace_id` correctly reflects "most recently accepted" without needing any synthetic ordering — no loop over multiple pending invites is required at all.

This also fixes a standing gap: `AcceptWorkspaceInviteController` today never touches `current_workspace_id`, so even an existing user accepting a click-through invite doesn't land in the new workspace by default.

### 4. Onboarding/choice screen replaces silent default-workspace creation

Any authenticated user with **zero accepted `user_workspace` memberships** (`UserWorkspace::where('user_id', $user->id)->exists()` is false) and no current workspace is redirected — by a new middleware, applied globally to authenticated web requests — to an onboarding/choice screen instead of having a workspace silently created for them. The screen:
- Lists any pending invitations for the user's email (accept action per invitation → calls `AcceptWorkspaceInvite::accept()`).
- Always offers a "create your own workspace" form, pre-filled with a suggested name derived from the user (e.g. `"{$user->name} Workspace"`, sanitized to the allowed character set — see Decision 8), which the user can edit before submitting — backed by the existing `CreateWorkspace::create()` action.

This applies uniformly regardless of whether the user has any pending invitations — an organic signup simply sees only the create-workspace form. If the user navigates away without acting, they still have no accepted membership, so the same guard redirects them back to this screen on their next request. No separate "hard gate" flag is needed — it falls out of the guard condition being evaluated on every request.

*Alternative considered*: keep `ensureHasDefaultWorkspace()`'s silent creation for organic signups, and only show a choice screen when pending invitations exist. Rejected in favor of one uniform mechanism — it removes the need to special-case "was this user invited or not," and matches the onboarding pattern of comparable products (Slack, Notion, Linear all show an explicit "create or join a workspace" step rather than silently provisioning one).

`ensureHasDefaultWorkspace()`'s creation branch (`HasWorkspaces.php:45-54`) becomes unreachable via the normal flow once this guard is in place; the method is kept only for its self-heal purpose (Decision 5) — repointing `current_workspace_id` to an existing membership when it's stale, which is a different scenario (the user already has ≥1 accepted membership, just not a valid *current* one).

### 5. `is_default` flag for deterministic self-heal

`user_workspace` gains an `is_default` boolean. It is:
- Set on a user's first-ever accepted membership, whether obtained by accepting an invitation or by creating their own workspace.
- **Re-set** whenever the user creates an *additional* workspace of their own via the panel (`CreateWorkspace::create()`) — a self-created workspace always supersedes the previous default. Since only one row per user may have `is_default = true`, `CreateWorkspace::create()` must unset the user's existing default (if any) and set the new one inside the same transaction.
- **Reassigned automatically** if the currently-default membership is removed (user leaves via `LeaveWorkspaceController`, or is removed via `WorkspaceUserController::destroy()`) while the user retains other memberships: the remaining membership with the earliest `created_at` is promoted to `is_default = true`.

`ensureHasDefaultWorkspace()`'s self-heal branch (and the equivalent branch in `HasWorkspaceMiddleware`) reads this flag directly — `UserWorkspace::where('user_id', $this->id)->where('is_default', true)->first()` — instead of the implicit `workspaces()->first()` ordering used today.

**Enforcing uniqueness on MySQL**: MySQL does not support filtered/partial unique indexes (`WHERE is_default = true`), unlike Postgres or SQLite. The migration instead adds a `STORED GENERATED` column, e.g.:

```sql
ALTER TABLE user_workspace
  ADD COLUMN default_owner_id BIGINT UNSIGNED
    GENERATED ALWAYS AS (CASE WHEN is_default THEN user_id ELSE NULL END) STORED;
CREATE UNIQUE INDEX uq_user_workspace_default_owner ON user_workspace (default_owner_id);
```

MySQL's unique index treats multiple `NULL`s as non-conflicting, so this enforces "at most one `is_default = true` row per `user_id`" while leaving `is_default = false` rows (which generate `NULL`) unconstrained.

*Alternative considered*: rely on `created_at ASC` ordering alone, with no new column, for self-heal (this is what the previous iteration of this design used for a related but different purpose — resolving `current_workspace_id` across multiple simultaneously-processed invitations, which no longer applies per Decision 3). Rejected for the self-heal case specifically because "oldest membership" isn't necessarily the user's intended home — e.g. a self-created workspace should outrank an older invited-into one — and because this design already commits to explicit, intentional state over implicit ordering for `current_workspace_id` resolution (Decision 3); doing the same for the self-heal anchor is consistent with that.

### 6. Normalize email at write time

`InviteToWorkspace::invite()` stores `Str::lower($input['email'])` rather than the raw admin input, since `users.email` is already lowercase-enforced at registration. This keeps the exact-match session/email verification in Decision 2 simple, without needing case-insensitive query logic at read time.

### 7. Resend is a distinct action, not a re-invite

A new resend action loads the existing pending `user_workspace` row, regenerates a signed URL with a fresh expiration, and re-sends `WorkspaceInvitation`, rather than going through `InviteToWorkspace::invite()` (which would be rejected by the existing per-workspace email uniqueness validation while the old row still exists).

### 8. Uniqueness enforced via a normalized slug, not the raw name

**Revised** (superseding the original "drop uniqueness entirely" decision below): dropping uniqueness entirely turned out to open a spoofing gap — a workspace named `Acme` and one named `ACME` (or `acme`, or `Acme ` with trailing whitespace) would both be created successfully with no error, letting an attacker create a workspace whose display name is visually indistinguishable from a legitimate one. `workspaces` gains a `slug` column (`Str::slug($name)`), unique-indexed at the DB level, which is what uniqueness is actually checked against — case, whitespace, and punctuation differences all normalize to the same slug, so they collide and the later one is rejected.

`name` itself stays a free-text display field, now allowed to contain letters (any case), numbers, dashes, and spaces (`regex:/^[A-Za-z0-9- ]+$/`), validated on both `CreateWorkspace` and `UpdateWorkspace` (previously only `UpdateWorkspace` had any uniqueness check at all, and it validated the raw, case-sensitive `name` — this was an inconsistency between the two entry points that the slug column also resolves, since both now go through the same `slug`-based `Rule::unique`).

A name that normalizes to an empty slug (e.g. all spaces, or all dashes) is rejected — `slug` is validated as `required` alongside `name`.

*Original decision (kept for context, no longer current)*: Remove `unique:workspaces,name` entirely — `Workspace` has no slug or custom route key (routing is plain numeric `id`), so `name` was treated as cosmetic only, and `ensureHasDefaultWorkspace()` already bypassed this validator, proving the constraint was never enforced end-to-end. This was walked back once the case-spoofing gap above was identified.

The personalized name from Decision 4 remains a suggested, editable pre-fill on the create-workspace form rather than a silently-applied value; it is not forced to lowercase, and any characters outside the allowed set (e.g. from a user's real name containing accents or punctuation) are stripped so the suggestion is always valid without the user needing to edit it first.

## Risks / Trade-offs

- [Risk] Every user, including organic signups, now hits an onboarding/choice screen instead of a zero-friction silent default. → Mitigation: explicitly accepted trade-off; the form is pre-filled with a suggested name, so it's a single confirm-and-submit action for the common case, not a blank form.
- [Risk] `is_default` requires active bookkeeping across three call sites (`AcceptWorkspaceInvite`, `CreateWorkspace`, and both removal paths) rather than a value that's correct by construction. → Mitigation: centralize the "unset old / set new" and "promote oldest remaining" logic in small, tested helper methods reused by all call sites, rather than duplicating the logic inline in each controller.
- [Risk] A resend leaves prior links for the same invite still valid until their own expiry. → Mitigation: explicitly accepted trade-off; deleting the underlying `user_workspace` row invalidates all outstanding links immediately regardless of their own signature's remaining validity, since the accept controller 404s once the row is gone.
- [Risk] Invitation links already emailed under the old `auth`-gated scheme become dead once this ships. → Mitigation: one-time transition; still-pending invitations can be resent via the new resend action after deploy.
- [Risk] Existing users (pre-migration) have no `is_default` row yet. → Mitigation: data-backfill during migration (see Migration Plan) — set `is_default = true` on the `user_workspace` row matching each user's current `current_workspace_id` where possible, falling back to their earliest-created membership.
- [Risk] `tests/Feature/WorkspaceTest.php` and `tests/Feature/WorkspaceUserCleanupTest.php` call `ensureHasDefaultWorkspace()` directly and assert the literal name `'default'`. → Mitigation: update those assertions during implementation (tracked in tasks.md).
- [Risk] Pre-existing `workspaces` rows may already contain names that only differ by case/whitespace, which would collide once normalized to a slug and reject the unique-index migration. → Mitigation: backfill assigns a numeric suffix (`-2`, `-3`, ...) to whichever colliding row is backfilled later, in ascending `id` order, so the migration always succeeds without deleting or merging any workspace; affected admins can rename post-migration if the auto-suffixed slug bothers them (the display `name` itself is untouched).

## Migration Plan

- New migration on `user_workspace`: add `is_default` boolean (default `false`), add the `default_owner_id` generated column, add its unique index.
- Data backfill (same migration or a follow-up step): for each user with an existing `current_workspace_id`, set `is_default = true` on their `user_workspace` row for that workspace; for users with no resolvable current workspace but at least one membership, set `is_default = true` on their earliest-created membership.
- New migration on `workspaces`: add `slug` column, backfill it for existing rows (`Str::slug(name)`, falling back to `'workspace'` for a row whose name normalizes to empty, with a numeric suffix — `-2`, `-3`, ... — appended in ascending `id` order whenever a backfilled slug collides with one already assigned), then add a unique index on `slug`.
- Rollback: dropping the new column/index is safe (no other data depends on it); reverting the code deploy restores the previous (buggy) behavior with no data corruption in either direction. Any invitation links sent under the new signed scheme would simply stop working post-rollback, same as the pre-existing rollback risk already noted for Decision 1.

## Open Questions

- Should the register page eventually surface "You're joining <Workspace Name>" messaging when arrived at via a signed invite link, ahead of the post-registration flow? Deferred — out of scope for this change.
- Should there be any UI affordance distinguishing "this is your default/home workspace" in the workspace switcher, now that the concept is explicit in the data model? Deferred — not required for this change's functional goals.
