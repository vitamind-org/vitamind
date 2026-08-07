## ADDED Requirements

### Requirement: Workspace GoWA instance is provisioned lazily
WakuWaku SHALL provision a workspace's dedicated GoWA instance only when the workspace's first WhatsApp number is added, not when the workspace itself is created.

#### Scenario: Workspace with no numbers has no instance
- **WHEN** a workspace exists but has never added a WhatsApp number
- **THEN** no GoWA instance has been provisioned for that workspace

#### Scenario: First number added triggers provisioning
- **WHEN** a workspace adds its first WhatsApp number
- **THEN** WakuWaku calls the provisioner's create-instance operation for that workspace before proceeding
- **AND** the resulting connection details are stored against the workspace

### Requirement: Instance creation is idempotent
The provisioner's create-instance operation SHALL be idempotent by workspace identifier: repeated calls for the same workspace SHALL return the existing instance's details rather than creating a duplicate or reallocating its port or credential.

#### Scenario: Create called twice for the same workspace
- **WHEN** a create-instance request is made for a workspace that already has an active instance
- **THEN** the response returns the existing instance's connection details unchanged
- **AND** no new port, folder, or credential is allocated

### Requirement: Instance creation is atomic
Instance creation SHALL either fully succeed (a running, healthy GoWA process reachable through its Kong route) or fully fail with all partially-created resources rolled back, so a failed attempt can be safely retried.

#### Scenario: Health check times out during creation
- **WHEN** a newly started instance does not respond healthy within the configured timeout
- **THEN** the systemd unit is stopped and disabled
- **AND** the instance's working directory is removed
- **AND** the create-instance request fails with an error

#### Scenario: Kong route sync fails after a healthy start
- **WHEN** the instance starts and passes its health check but the Kong routing update fails
- **THEN** the instance is rolled back (stopped, disabled, working directory removed)
- **AND** the workspace is left with no instance rather than an unrouted running one

### Requirement: Each workspace instance is isolated at the process and credential level
Each workspace's GoWA instance SHALL run as its own OS process with its own port, its own session/chat storage, and its own generated Basic Auth credential, distinct from every other workspace's instance.

#### Scenario: Two workspaces provisioned
- **WHEN** two different workspaces each provision an instance
- **THEN** each instance runs on a different port with a different generated Basic Auth credential
- **AND** neither instance's storage directory is shared with the other

### Requirement: Workspace identifiers are validated before use
The provisioner SHALL reject a create-instance, status, or delete-instance request whose workspace identifier does not match the pattern `^[a-z0-9][a-z0-9_-]{0,62}$`, before it is used to construct any filesystem path, systemd unit name, or shell command.

#### Scenario: Invalid workspace identifier rejected
- **WHEN** a request is made with a workspace identifier containing characters outside `[a-z0-9_-]` or exceeding the length limit
- **THEN** the request is rejected without touching the filesystem or invoking any subprocess

### Requirement: WakuWaku is given a directly-usable connection to the workspace's instance
On successful creation, WakuWaku SHALL receive and persist a publicly-reachable base URL, routed through Kong rather than a `localhost` address, and the instance's Basic Auth credential, sufficient to call that instance's GoWA API directly without going through the provisioner.

#### Scenario: Base URL is externally reachable
- **WHEN** WakuWaku receives a successful create-instance response
- **THEN** the returned base URL is reachable from WakuWaku's network location through the public Kong route
- **AND** is distinct from the instance's internal `localhost` port

### Requirement: Instance status can be queried independently of provisioning
The provisioner SHALL expose a way to query an existing instance's current state (active, unhealthy, or absent) without side effects.

#### Scenario: Status check on a healthy instance
- **WHEN** a status request is made for a workspace with a running, healthy instance
- **THEN** the response indicates the instance is active

#### Scenario: Status check on a workspace with no instance
- **WHEN** a status request is made for a workspace that has never been provisioned
- **THEN** the response indicates no instance exists, without creating one

### Requirement: Instance deletion removes routing together with resource teardown
Deleting a workspace's instance SHALL stop and disable its systemd unit and remove its Kong route as part of the same operation, so no traffic can reach a torn-down instance.

#### Scenario: Instance deleted
- **WHEN** a delete-instance request is made for an active workspace instance
- **THEN** the instance's systemd unit is stopped and disabled
- **AND** its Kong route no longer appears in the routing configuration

### Requirement: Provisioner control-plane access is restricted to WakuWaku
The provisioner's HTTP API SHALL be reachable only from WakuWaku's known outbound network address and SHALL require a valid bearer credential on every request; it SHALL NOT process a request missing either check.

#### Scenario: Request from an unlisted address
- **WHEN** a request to the provisioner API arrives from an address other than WakuWaku's configured outbound address
- **THEN** the request is rejected before reaching the provisioner's own logic

#### Scenario: Request without a valid bearer credential
- **WHEN** a request to the provisioner API is made without a valid bearer credential
- **THEN** the request is rejected before reaching the provisioner's own logic

### Requirement: Provisioner has no knowledge of individual WhatsApp numbers
The provisioner SHALL only manage instance lifecycle (create, status, delete, health) and SHALL NOT expose, proxy, or track any operation scoped to an individual WhatsApp number or device.

#### Scenario: Device-level operation is not available on the provisioner
- **WHEN** WakuWaku needs to add, link, or check an individual WhatsApp number
- **THEN** it calls the workspace's GoWA instance directly using the stored base URL and credential
- **AND** no such operation exists on the provisioner's own API
