## ADDED Requirements

### Requirement: Adding a WhatsApp number ensures the workspace's instance exists
Adding a WhatsApp number to a workspace SHALL ensure that workspace's GoWA instance is provisioned, creating it if this is the first number, before the number is registered as a device on that instance.

#### Scenario: First number added to a fresh workspace
- **WHEN** a user adds the first WhatsApp number to a workspace with no existing instance
- **THEN** the workspace's GoWA instance is provisioned
- **AND** a new device is created on that instance for the number

#### Scenario: Additional number added to a workspace with an existing instance
- **WHEN** a user adds another WhatsApp number to a workspace that already has an active instance
- **THEN** no new instance is provisioned
- **AND** a new device is created on the existing instance

### Requirement: A number can be linked via QR code
WakuWaku SHALL support linking a WhatsApp number to its device by retrieving a QR code from the workspace's GoWA instance and presenting it for the user to scan.

#### Scenario: User requests QR login
- **WHEN** a user starts the linking flow for an unlinked number
- **THEN** WakuWaku requests a QR code from the number's device on the workspace's GoWA instance
- **AND** presents the QR code to the user until it is scanned or expires

### Requirement: A number can be linked via pairing code
WakuWaku SHALL support linking a WhatsApp number by requesting a pairing code from the workspace's GoWA instance for the number's phone number, as an alternative to QR scanning.

#### Scenario: User requests pairing-code login
- **WHEN** a user chooses pairing-code linking for an unlinked number and supplies its phone number
- **THEN** WakuWaku requests a pairing code from the number's device on the workspace's GoWA instance
- **AND** presents the code to the user to enter on their phone

### Requirement: Number status reflects the underlying device state
WakuWaku SHALL be able to report a number's current connection state — disconnected, connecting, connected, or logged in — by querying its device on the workspace's GoWA instance.

#### Scenario: User views number status
- **WHEN** a user views a WhatsApp number they have added
- **THEN** WakuWaku shows the current state reported by the number's device on the workspace's GoWA instance

### Requirement: A number can be unlinked without losing its record
Unlinking a number SHALL log its device out of WhatsApp, clearing its session, while keeping the device slot and the number's record in WakuWaku so it can be relinked under the same identity.

#### Scenario: User unlinks a number
- **WHEN** a user unlinks a connected WhatsApp number
- **THEN** the number's device is logged out on the workspace's GoWA instance
- **AND** the number's record remains in WakuWaku and can be relinked

### Requirement: A number can be deleted, purging its session
Deleting a number SHALL remove its device entirely from the workspace's GoWA instance, including its session and chat data, as well as its record in WakuWaku, distinct from unlinking.

#### Scenario: User deletes a number
- **WHEN** a user deletes a WhatsApp number from their workspace
- **THEN** the number's device and its session and chat data are removed from the workspace's GoWA instance
- **AND** the number's record is removed from WakuWaku

### Requirement: Remote unlink events are reflected without a WakuWaku-initiated action
When a number is unlinked directly from the WhatsApp phone app rather than through WakuWaku, WakuWaku SHALL detect and reflect this state change via the webhook events received from the owning workspace's GoWA instance.

**Status: not currently implementable.** Verified directly against GoWA v9's source (`src/infrastructure/whatsapp/event_handler.go`): a phone-initiated logout only broadcasts over GoWA's internal WebSocket (`DEVICE_LOGGED_OUT`) — it is never forwarded to the configured HTTP webhook, unlike every other event type. This requirement is left unimplemented (see `design.md`'s Implementation Addendum) pending either an upstream GoWA change or a different delivery mechanism (e.g. status polling) in a future change.

#### Scenario: User unlinks from their phone
- **WHEN** a user unlinks a number directly from the WhatsApp app on their phone
- **THEN** the workspace's GoWA instance reports the logout event to WakuWaku's webhook receiver
- **AND** WakuWaku updates the number's status to reflect it is no longer linked

### Requirement: Webhook events are attributed to the correct workspace and number
WakuWaku SHALL expose a single webhook endpoint that receives events from every workspace's GoWA instance and attributes each event to the correct workspace and WhatsApp number using an identifier carried in the event payload.

#### Scenario: Event received from one of several workspace instances
- **WHEN** WakuWaku's webhook endpoint receives an event
- **THEN** it is attributed to the workspace and number matching the identifier in the event payload
- **AND** is not applied to a different workspace's number

### Requirement: Numbers are scoped to their owning workspace
A WhatsApp number's record SHALL be scoped to the workspace that added it and SHALL NOT be visible to or modifiable by a different workspace.

#### Scenario: User views numbers in their workspace
- **WHEN** a user views the WhatsApp numbers in their current workspace
- **THEN** only numbers added by that workspace are shown
- **AND** numbers belonging to other workspaces are not visible or reachable
