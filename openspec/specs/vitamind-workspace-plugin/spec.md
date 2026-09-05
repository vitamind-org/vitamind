# vitamind-workspace-plugin Specification

## Purpose

Workspace Plugin (`vitamind/workspace-plugin`) is the optional, feature-flagged package that adds multi-tenancy workspace/project support on top of VitaminD Core. It depends on core but core has no knowledge of it, so single-tenant applications can omit the plugin entirely with zero database or runtime overhead. When enabled, it provides workspace CRUD, membership management, a workspace switcher UI, access-control middleware, and Inertia shared data so the frontend can render workspace-aware views.

## Requirements

### Requirement: Workspace Plugin is optional and feature-flagged

Workspace Plugin SHALL be an optional plugin (`vitamind/workspace-plugin`) that adds multi-tenancy workspace/project support. It SHALL only activate when environment variable `VITAMIND_FEATURE_WORKSPACES=true` is set, and SHALL have zero impact on single-tenant applications. The plugin's service provider SHALL be registered purely through Composer package auto-discovery (`extra.laravel.providers`) — no manual edit to the host application's `bootstrap/providers.php` SHALL be required to enable or disable the plugin. The feature flag it reads (`vitamin-d.features.workspaces`) SHALL resolve to a real boolean (from `vitamind/core`'s shipped default configuration) even when the host application has not published or created its own `config/vitamin-d.php`.

#### Scenario: Developer installs workspace plugin
- **WHEN** developer runs `composer require vitamind/workspace-plugin`
- **THEN** plugin is installed and its service provider is auto-discovered
- **AND** no database tables are created until feature flag is enabled
- **AND** no routes or middleware are registered yet
- **AND** the host application does not need to edit `bootstrap/providers.php`

#### Scenario: Feature flag enables workspace functionality on a fresh installation
- **WHEN** developer sets `VITAMIND_FEATURE_WORKSPACES=true` in `.env` and runs migrations, in a project that has not published or created its own `config/vitamin-d.php`
- **THEN** the flag still resolves to `true` (via `vitamind/core`'s shipped default config, which reads the same environment variable)
- **AND** workspace tables (workspaces, user_workspaces) are created
- **AND** workspace middleware and routes become active
- **AND** workspace switcher appears in UI

#### Scenario: Feature flag disabled removes workspace functionality
- **WHEN** developer leaves `VITAMIND_FEATURE_WORKSPACES=false` (default)
- **THEN** workspace tables are not created
- **AND** workspace routes are not registered
- **AND** application behaves as single-tenant
- **AND** zero workspace-related database overhead

### Requirement: Workspace Plugin is independent from Core

Workspace Plugin SHALL depend on `vitamind/core` but core SHALL have no knowledge of workspace plugin. Plugin registration uses event-based or registry pattern so that core remains lean.

#### Scenario: Core bootstraps without workspace knowledge
- **WHEN** core boots
- **THEN** it does not import or reference workspace classes
- **AND** plugin is loaded separately via its own service provider
- **AND** core can function completely without workspace package installed

### Requirement: Workspace CRUD operations are fully functional

Workspace Plugin SHALL provide complete CRUD (Create, Read, Update, Delete) operations for workspaces, including user invitations, invitation revocation, invitation resending, leave workspace, and user removal. A workspace's owner SHALL be tracked as a plain attribute of the workspace (not a role), set once at creation. Workspace-scoped roles an application chooses to register via the `multi-role-authorization` mechanism (`RegisterRole` + `user_roles`) MAY be assigned to members through the invitation flow, but Workspace Plugin itself SHALL NOT register or depend on any built-in role for its own CRUD authorization.

#### Scenario: Admin creates a new workspace
- **WHEN** admin accesses workspace management page and creates new workspace
- **THEN** workspace record is created in database
- **AND** the creating user is recorded as the workspace's owner
- **AND** workspace is immediately accessible from workspace switcher
- **AND** the newly created workspace becomes the user's default (anchor) membership, superseding any previous default

#### Scenario: User is invited to workspace
- **WHEN** workspace owner invites a user by email, optionally selecting either system Admin access or one registered role from the invite dropdown
- **THEN** an invitation record (`user_workspace` row with the invitee's normalized, lowercased email and `user_id` NULL) is created, carrying the selected choice (a role, an Admin grant, or neither)
- **AND** an invitation email is sent containing a signed, time-limited link scoped to that specific invitation
- **AND** an already-registered user who opens the link while authenticated as the invited email is attached to the workspace immediately, granted system Admin access or assigned the selected role scoped to that workspace if one was chosen, and their current workspace switches to it
- **AND** a not-yet-registered visitor who opens the link is directed to registration instead of being blocked by an authentication requirement, and only that specific invitation is later auto-accepted, applying the same choice once registration completes

#### Scenario: User leaves workspace
- **WHEN** user clicks "Leave Workspace"
- **THEN** user is removed from workspace
- **AND** any workspace-scoped role assignments the user held for that workspace are removed
- **AND** user is redirected to dashboard or another workspace
- **AND** user's tasks/permissions in that workspace are cleaned up
- **AND** if the left workspace was the user's default membership and other memberships remain, the remaining membership with the earliest `created_at` is automatically promoted to default

#### Scenario: Admin revokes a pending invitation
- **WHEN** admin deletes a pending (not-yet-accepted) invitation
- **THEN** the invitation record is removed
- **AND** any previously issued invitation link for it stops working, regardless of whether the link's own signed expiry has passed

#### Scenario: Admin resends a pending invitation
- **WHEN** admin resends a pending invitation (`user_id` is `NULL`), expired or not
- **THEN** the existing invitation record is reused (no duplicate record is created)
- **AND** a new signed, time-limited link is generated and emailed
- **AND** any previously issued link for the same invitation remains independently valid until its own expiry

#### Scenario: Admin removes a user from their default workspace
- **WHEN** admin removes a user whose membership in that workspace was flagged as their default, and the user retains other memberships
- **THEN** the remaining membership with the earliest `created_at` is automatically promoted to default
- **AND** if no other memberships remain, the user has no default membership until they accept a new invitation or create a workspace

#### Scenario: The workspace owner cannot be removed
- **WHEN** anyone attempts to remove the workspace's owner from that workspace
- **THEN** the removal is rejected
- **AND** the owner's membership and ownership remain unchanged

### Requirement: Workspace ownership is plain data, independent of the role mechanism

Each workspace SHALL have exactly one owner, recorded directly on the workspace (not as a role assignment), set once when the workspace is created and not reassignable through any existing flow. Ownership SHALL be entirely independent of `multi-role-authorization` — no `RegisterRole` entry SHALL represent "owner," and owner status SHALL NOT be revocable or grantable through the invitation flow.

#### Scenario: Workspace creation sets the owner
- **WHEN** a workspace is created
- **THEN** the creating user is recorded as its owner at that moment
- **AND** no role assignment is created to represent this

#### Scenario: Ownership cannot be granted through an invitation
- **WHEN** a workspace owner sends an invitation
- **THEN** there is no way to select "owner" as the invitation's outcome — the invite dropdown offers only system Admin access or a registered role, never ownership

### Requirement: Workspace invitations can optionally grant system Admin access or any registered role

Workspace Plugin SHALL present the invitation role selector as an optional, single choice among a fixed **Admin** option and every role currently registered via `RegisterRole`. Selecting Admin SHALL grant the invitee `is_admin` (a global, cross-workspace privilege) instead of a workspace-scoped role assignment. Selecting a registered role SHALL assign that role scoped to the invited workspace. Selecting neither SHALL be valid, leaving the invitee a plain member with no role assignment and no `is_admin` grant. The three SHALL be mutually exclusive per invitation.

#### Scenario: Invite dropdown reflects the current role registry
- **WHEN** an application has registered roles via `RegisterRole` (e.g. a domain-specific role such as `admin-gudang`)
- **THEN** each of those roles appears as a selectable option on the workspace invite form without any workspace-plugin-specific code change

#### Scenario: Invitation grants system Admin instead of a workspace role
- **WHEN** an inviter selects the Admin option for an invitation
- **THEN** no workspace-scoped role is assigned to the invitee on acceptance
- **AND** the invitee's `is_admin` flag is set to `true` on acceptance instead

#### Scenario: Invitation grants a registered role instead of system Admin
- **WHEN** an inviter selects a registered role for an invitation
- **THEN** the invitee is assigned that role scoped to the invited workspace on acceptance
- **AND** the invitee's `is_admin` flag is left unchanged

#### Scenario: Invitation grants neither Admin nor a role
- **WHEN** an inviter sends an invitation without selecting Admin or any role — including when the application has not registered any roles at all
- **THEN** the invitation is still valid and can be sent and accepted
- **AND** on acceptance the invitee becomes a plain member of the workspace, with no role assignment and no `is_admin` grant

### Requirement: Users without an accepted workspace membership choose their workspace explicitly

Workspace Plugin SHALL NOT silently auto-provision a workspace for any authenticated user who has zero accepted `user_workspace` memberships, regardless of whether they were invited. Such a user SHALL be routed to an onboarding/choice screen offering to accept a pending invitation or create their own workspace, and SHALL continue to be routed there on subsequent requests until they act.

#### Scenario: User registers with a pending invitation and never opens the invitation link
- **WHEN** a user registers with an email address that has one or more pending invitations, without ever visiting a signed invitation link
- **THEN** no invitation is automatically accepted on their behalf
- **AND** the user is routed to the onboarding/choice screen listing their pending invitation(s) and a create-workspace option

#### Scenario: User registers with no pending invitations
- **WHEN** a user registers with an email address that has no pending invitations
- **THEN** no workspace is silently created for them
- **AND** the user is routed to the onboarding/choice screen showing only the create-workspace option, pre-filled with a suggested personalized name

#### Scenario: User ignores the onboarding/choice screen
- **WHEN** a user with no accepted workspace membership navigates away from the onboarding/choice screen without accepting an invitation or creating a workspace
- **THEN** they remain without a current workspace
- **AND** their next request is routed back to the onboarding/choice screen

#### Scenario: User accepts an invitation from the onboarding/choice screen
- **WHEN** a user on the onboarding/choice screen accepts one of their listed pending invitations
- **THEN** the user is attached to that workspace and no others, even if other pending invitations exist
- **AND** the user's current workspace is set to the accepted workspace

#### Scenario: Already-registered user with an active workspace is invited
- **WHEN** an existing user who already has an accepted workspace membership opens a valid invitation link while authenticated as the invited email
- **THEN** they are attached to the invited workspace and their current workspace switches to it
- **AND** they are not routed through the onboarding/choice screen

### Requirement: A specific invitation is auto-accepted only when explicitly referenced

Workspace Plugin SHALL auto-accept an invitation upon registration only when the registering visitor arrived via that specific invitation's signed link. No other pending invitation sharing the same email SHALL be attached without explicit user action.

#### Scenario: User has two pending invitations and clicks the link for one
- **WHEN** a user with pending invitations to two different workspaces clicks the signed link for one of them and then registers
- **THEN** only the invitation referenced by the clicked link is automatically accepted
- **AND** the other pending invitation remains unaccepted and appears on the onboarding/choice screen

#### Scenario: User has exactly one pending invitation but registers without opening its link
- **WHEN** a user registers directly at `/register` without visiting the signed link for their one pending invitation
- **THEN** that invitation is not automatically accepted
- **AND** it appears on the onboarding/choice screen for explicit acceptance

### Requirement: Invitation acceptance does not require a prior authenticated session

Workspace Plugin SHALL make the invitation acceptance link reachable by a visitor with no existing account or session, using a signed, time-limited URL scoped to the specific invitation rather than requiring the visitor to already be authenticated.

#### Scenario: Unregistered invitee opens an invitation link
- **WHEN** a visitor with no existing account opens a valid, unexpired invitation link
- **THEN** they are directed toward registration instead of receiving an authentication error
- **AND** completing registration with the invited email later auto-accepts that specific invitation

#### Scenario: Invitation link has expired
- **WHEN** a visitor opens an invitation link after its signed expiration has passed
- **THEN** access is rejected

#### Scenario: Invitation link is tampered with
- **WHEN** a visitor opens an invitation link whose workspace or invitation identifier has been altered from what was originally signed
- **THEN** access is rejected

### Requirement: A user's default workspace membership is explicitly tracked

Workspace Plugin SHALL track which single `user_workspace` membership is a user's default (anchor) membership, enforced as unique per user, and SHALL use it to deterministically resolve the user's current workspace when it becomes invalid.

#### Scenario: First accepted membership becomes the default
- **WHEN** a user's first-ever accepted membership is created, whether by accepting an invitation or by creating their own workspace
- **THEN** that membership is flagged as their default

#### Scenario: Self-created workspace supersedes a previous default
- **WHEN** a user who already has a default membership creates an additional workspace of their own via the workspace panel
- **THEN** the newly created workspace becomes their default
- **AND** the previous default membership is no longer flagged as default

#### Scenario: A user cannot have two default memberships at once
- **WHEN** the system attempts to flag a second membership as default for a user who already has one
- **THEN** the previous default is unset in the same operation, so at most one default membership exists per user at all times

#### Scenario: Default membership is used to resolve a stale current workspace
- **WHEN** a user's `current_workspace_id` no longer resolves to a workspace they belong to
- **THEN** the system resolves their current workspace to the membership flagged as default, rather than relying on unordered query results

### Requirement: Workspace names are restricted and normalized to a unique slug

Workspace Plugin SHALL restrict workspace names to letters (any case), numbers, dashes, and spaces, and SHALL enforce uniqueness via a normalized slug derived from the name rather than the raw name itself, so names differing only by letter case, whitespace, or punctuation cannot coexist.

#### Scenario: Name contains disallowed characters
- **WHEN** a user submits a workspace name containing a character other than a letter, number, dash, or space
- **THEN** validation fails with an error explaining which characters are allowed
- **AND** no workspace record is created or updated

#### Scenario: Name normalizes to an empty slug
- **WHEN** a user submits a workspace name consisting only of spaces and/or dashes
- **THEN** validation fails, since no meaningful slug can be derived from the name

#### Scenario: Two workspace names normalize to the same slug
- **WHEN** a user creates or renames a workspace to a name whose normalized slug matches an existing workspace's slug, differing only in letter case, spacing, or punctuation from that existing name
- **THEN** validation fails with an error indicating the name is already in use
- **AND** no duplicate workspace is created

#### Scenario: Two workspace names normalize to different slugs
- **WHEN** two different users each create a workspace with names that normalize to different slugs
- **THEN** both workspace records are created successfully

### Requirement: Auto-suggested workspace name is personalized

Workspace Plugin SHALL pre-fill the create-workspace form on the onboarding/choice screen with a name suggestion derived from the user's identity, editable before submission.

#### Scenario: User creates their first workspace via the onboarding/choice screen
- **WHEN** a user with no pending invitations reaches the onboarding/choice screen
- **THEN** the create-workspace form is pre-filled with a name derived from the user (e.g. includes the user's name), preserving the user's original letter case rather than forcing lowercase
- **AND** the user may edit this suggested name before submitting

### Requirement: Workspace Switcher is accessible in UI

Workspace Plugin SHALL provide UI component for switching between workspaces, visible in app header when feature enabled.

#### Scenario: User switches between workspaces
- **WHEN** user with multiple workspace memberships clicks workspace switcher
- **THEN** list of workspaces user is member of is displayed
- **AND** clicking a workspace loads it and switches current_workspace context
- **AND** page reloads with new workspace data

### Requirement: Workspace middleware prevents unauthorized access

Workspace Plugin SHALL enforce that authenticated requests have an active workspace context, preventing access to other workspaces' data. Read access to a workspace SHALL require an existing membership (a `user_workspace` row); write access — updating workspace settings, inviting or removing members, deleting the workspace — SHALL require being that workspace's owner. Workspace Plugin SHALL NOT grant write access to a non-owner member through any built-in tier; an application that wants a non-owner member to have elevated workspace-management capability MUST implement that itself via its own registered role and policy logic. `is_admin` SHALL NOT be consulted for workspace-level authorization.

#### Scenario: Request without active workspace is blocked
- **WHEN** authenticated user makes request without active workspace context
- **THEN** system redirects to workspace selection or home
- **AND** data is not leaked across workspace boundaries

#### Scenario: User cannot access workspace data without membership
- **WHEN** user attempts to access resources from workspace they are not member of
- **THEN** system returns 403 Forbidden
- **AND** no data is exposed

#### Scenario: A non-owner member cannot manage the workspace
- **WHEN** a workspace member who is not its owner attempts to update workspace settings, invite a member, remove a member, or delete the workspace
- **THEN** the action is rejected
- **AND** this holds regardless of any role the member has been assigned, since Workspace Plugin's own authorization does not consult role assignments

### Requirement: Workspace is shared with Inertia frontend

Workspace Plugin SHALL share current active workspace data with React frontend via Inertia shared props, enabling frontend to render workspace-aware UI.

#### Scenario: Frontend receives current workspace data
- **WHEN** page is rendered via Inertia
- **THEN** `auth.currentWorkspace` is available in props
- **AND** frontend components can display workspace name, member count, etc.
- **AND** workspace context persists across page navigation
