## MODIFIED Requirements

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

## ADDED Requirements

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
