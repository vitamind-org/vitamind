## ADDED Requirements

### Requirement: New users are restricted to a single workspace
WakuWaku SHALL prevent an authenticated user who already belongs to a workspace from creating an additional workspace or accepting an invitation to another workspace.

#### Scenario: User with existing workspace attempts to create a new workspace
- **WHEN** a user who already has a workspace submits a request to create a new workspace
- **THEN** the request is rejected before a new workspace record is created
- **AND** the user's existing workspace remains their only workspace

#### Scenario: User with existing workspace attempts to accept an invitation
- **WHEN** a user who already belongs to a workspace attempts to accept an invitation to a different workspace
- **THEN** the request is rejected
- **AND** the user's membership in their existing workspace is unchanged

### Requirement: Enforcement lives in the WakuWaku application layer
The policy SHALL be enforced by WakuWaku's own application code, not by modifying `dev-packages/vitamind-workspace-plugin`, so the shared package's default multi-workspace-per-user behavior remains available to other VitaminD-based projects.

#### Scenario: vitamind-workspace-plugin behavior is unaffected
- **WHEN** the single-workspace-tenancy policy is active in WakuWaku
- **THEN** `dev-packages/vitamind-workspace-plugin`'s workspace-creation and invitation actions remain unchanged
- **AND** a project using `vitamind-workspace-plugin` without this policy gate still allows multiple workspaces per user

### Requirement: New users choose their one workspace via onboarding
WakuWaku SHALL route each authenticated user with zero workspace memberships to an explicit onboarding choice (create their own workspace, or accept a pending invitation) rather than silently assigning one, consistent with the one-workspace-per-user constraint. This supersedes an earlier version of this requirement that called for automatic silent assignment — that mechanism (`ensureHasDefaultWorkspace()` running as a side effect of every authenticated request) made invite acceptance permanently unreachable once a "reject if already has a workspace" gate existed, since the auto-assignment always ran first. See `dev-packages/vitamind-workspace-plugin`'s `EnsureWorkspaceOnboarded` middleware and `WorkspaceOnboardingController` (merged from `origin/dev`, archived at `openspec/changes/archive/2026-08-06-fix-workspace-invite-onboarding`).

#### Scenario: New user signs up with no pending invitation
- **WHEN** a new user signs up and has no workspace yet and no pending invitation
- **THEN** they are routed to the onboarding screen and offered a create-workspace form pre-filled with a suggested name
- **AND** once they create it, it becomes their only workspace and no further workspace-creation action is available to them

#### Scenario: New user signs up with a pending invitation
- **WHEN** a new user has no workspace yet and has a pending invitation matching their email
- **THEN** they are routed to the onboarding screen and offered that invitation to accept
- **AND** once accepted, the invited workspace becomes their only workspace and no further workspace-creation or invitation-acceptance action is available to them
