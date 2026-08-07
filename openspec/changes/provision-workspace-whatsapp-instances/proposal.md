## Why

WakuWaku lets each workspace connect and manage multiple WhatsApp numbers through GoWA (`aldinokemal/go-whatsapp-web-multidevice`), but two things stand in the way today: GoWA itself has no concept of a tenant (one Basic Auth credential governs an entire process), and the existing provisioning tooling (`waini-baremetal`) is scoped to one OS process per phone number — not per organization. Neither is usable as-is for a self-service product where organizations add and remove numbers on demand while staying isolated from each other.

## What Changes

- Repurpose `waini-baremetal`'s systemd + Kong tooling from one-process-per-phone-number to **one-process-per-workspace**: a single GoWA instance (own port, own storage, own Basic Auth credential) serves every WhatsApp number a workspace adds, matching GoWA v9's own multi-device-per-process model.
- Introduce a small Go **provisioner service** (`waini-provisioner`, a new component inside the `waini-baremetal` repo) exposing an authenticated instance-lifecycle API — create, status, delete, health — fronted by Kong. This service owns OS-level provisioning (folder/env setup, systemd unit management, port allocation) and Kong route synchronization; it has no knowledge of individual WhatsApp numbers.
- Migrate Kong from traditional (Postgres-backed) mode to **DB-less declarative mode**: routing updates become "regenerate the full config, POST once to `/config`" instead of incremental Admin API calls, removing the Postgres dependency entirely.
- Add WakuWaku-side storage mapping each workspace to its provisioned GoWA instance (base URL, Basic Auth credential, state).
- Add WakuWaku-side WhatsApp number management: adding a number triggers lazy provisioning of the workspace's instance (if not already provisioned), then talks **directly** to that instance's GoWA API (not through the provisioner) for QR/pairing-code linking, status, unlink (logout, session kept), and delete (full purge).
- Add a single shared webhook receiver in WakuWaku that ingests GoWA events from every workspace instance, disambiguating by the device/workspace identifier in the payload.
- **BREAKING** (WakuWaku app UX only): add a policy gate restricting each WakuWaku user to exactly one workspace. This is enforced entirely in WakuWaku's own application layer — `dev-packages/vitamind-workspace-plugin`, which defaults to multi-workspace-per-user, is left unmodified so other VitaminD-based projects keep that default.

Out of scope for this change: chat, contact, and message management (a later phase). This change covers account/number lifecycle only.

## Capabilities

### New Capabilities
- `single-workspace-tenancy`: enforces one workspace per WakuWaku user via an application-level policy gate, without changing `vitamind-workspace-plugin`'s own (multi-workspace) default behavior.
- `whatsapp-workspace-provisioning`: lifecycle of a workspace's dedicated GoWA instance — the lazy-provisioning trigger from WakuWaku, the `waini-provisioner` API contract (create/status/delete/health, idempotency, rollback semantics), the Kong DB-less routing/auth model, and WakuWaku's stored per-workspace connection record.
- `whatsapp-number-management`: WakuWaku-side lifecycle of individual WhatsApp numbers within a workspace's GoWA instance — add number, QR/pairing-code linking, status, unlink (logout/keep-slot), delete (purge), and webhook event ingestion routed back to the owning workspace.

### Modified Capabilities
(none — `vitamind-workspace-plugin` and other existing specs are unchanged by this proposal)

## Impact

- New Laravel migrations/models: a per-workspace GoWA connection record (base URL, Basic Auth credential, state) and a `WaNumber` model using `dev-packages/vitamind-plugin-sdk`'s `BelongsToWorkspace` trait (same pattern as `Todo` in `vitamind-todo-plugin`).
- New policy/gate code in WakuWaku's own app layer (not in `dev-packages/vitamind-workspace-plugin`) intercepting workspace-creation and invite-acceptance flows to enforce one workspace per user.
- Two new outbound HTTP clients in WakuWaku: one for the `waini-provisioner` instance-lifecycle API, one for GoWA's own `/devices/*` API called directly per workspace using its stored base URL and credential.
- New inbound webhook route/controller in WakuWaku for GoWA events.
- Cross-repo dependency: this change documents a contract that a separate, private repo (`waini-baremetal`, not checked out in this workspace) must implement on its side — the Kong DB-less migration, the `whatsapp@.service` template's shift from per-number to per-workspace, and the new `waini-provisioner` Go service. That repo's own implementation is out of this change's edit scope; the `design.md` and `specs/` here are the reference contract for it.
- External dependency: GoWA v9.0.0 (`aldinokemal/go-whatsapp-web-multidevice`) REST API and its device state model.
