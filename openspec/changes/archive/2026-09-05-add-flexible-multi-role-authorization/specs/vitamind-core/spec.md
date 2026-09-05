## ADDED Requirements

### Requirement: Admin-panel user creation and editing support multi-role assignment independent of the Admin toggle

`vitamind-core`'s `CreateUser`/`UpdateUser` actions SHALL accept zero or more role keys, each validated against the currently registered `RegisterRole` keys, and SHALL assign each submitted role to the user via the `multi-role-authorization` mechanism (`user_roles`), scoped through whichever `RoleScopeResolver` is currently bound. Independently of role selection, these actions SHALL retain a separate `is_admin` toggle. The two SHALL be orthogonal: a submission MAY set any combination of roles and the `is_admin` flag, including both, either alone, or neither.

#### Scenario: Creating a user with one or more roles
- **WHEN** an admin creates a user and selects one or more currently registered roles
- **THEN** the user is created
- **AND** a `user_roles` assignment is created for each selected role, scoped through the currently bound `RoleScopeResolver`

#### Scenario: Selecting a role that is not currently registered
- **WHEN** an admin submits a role key that does not exist in the current `RegisterRole` registry
- **THEN** validation fails and no user is created or updated

#### Scenario: Granting Admin access is independent of role selection
- **WHEN** an admin creates or updates a user, setting the Admin toggle and selecting one or more roles in the same submission
- **THEN** the user's `is_admin` flag is set to `true`
- **AND** the selected roles are also assigned
- **AND** neither choice is overridden or excluded by the other

#### Scenario: Creating a user with no roles and no Admin access
- **WHEN** an admin creates a user without selecting any role and without setting the Admin toggle
- **THEN** the user is created with no role assignments and `is_admin` set to `false`
