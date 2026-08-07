## MODIFIED Requirements

### Requirement: Workspace CRUD operations are fully functional

Workspace Plugin SHALL provide complete CRUD (Create, Read, Update, Delete) operations for workspaces, including user invitations, invitation revocation, invitation resending, leave workspace, and user removal.

#### Scenario: Admin creates a new workspace
- **WHEN** admin accesses workspace management page and creates new workspace
- **THEN** workspace record is created in database
- **AND** creating user is set as workspace owner
- **AND** workspace is immediately accessible from workspace switcher
- **AND** the newly created workspace becomes the user's default (anchor) membership, superseding any previous default

#### Scenario: User is invited to workspace
- **WHEN** workspace owner or admin invites a user by email
- **THEN** an invitation record (`user_workspace` row with the invitee's normalized, lowercased email and `user_id` NULL) is created
- **AND** an invitation email is sent containing a signed, time-limited link scoped to that specific invitation
- **AND** an already-registered user who opens the link while authenticated as the invited email is attached to the workspace immediately and their current workspace switches to it
- **AND** a not-yet-registered visitor who opens the link is directed to registration instead of being blocked by an authentication requirement, and only that specific invitation is later auto-accepted

#### Scenario: User leaves workspace
- **WHEN** user clicks "Leave Workspace"
- **THEN** user is removed from workspace
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

## ADDED Requirements

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
