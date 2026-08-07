## Context

WakuWaku is multi-tenant: each user belongs to exactly one workspace, and a workspace can manage many WhatsApp numbers. Connectivity is provided by GoWA (`aldinokemal/go-whatsapp-web-multidevice` v9.0.0), an unofficial WhatsApp Web gateway. Two facts about GoWA, confirmed against its v9.0.0 source and `docs/openapi.yaml`, drive this design:

- A single GoWA process can host many WhatsApp accounts ("devices") at once, each identified by a caller-chosen `device_id`, via its `DeviceManager` and a shared `whatsmeow` session store (`DBURI`, sqlite- or Postgres-backed).
- GoWA has no tenant concept at the API layer: authentication is a single HTTP Basic Auth credential (`APP_BASIC_AUTH`) per running process, not per device.

Separately, the operator already has a private repo, `waini-baremetal` ("waini"), that provisions and fronts GoWA processes on a bare-metal host using systemd (`whatsapp@.service` template) and Kong (path-based routing) behind Caddy (SSL). It currently assumes **one process per phone number** — a model from before GoWA supported multiple devices per process — and Kong is deployed in traditional, Postgres-backed mode.

`waini-baremetal` is a separate, private repository not checked out in this workspace. This document is the reference contract for the work that must happen there; it does not implement it.

**This is not a greenfield infrastructure migration.** As of 2026-08-02, `waini-baremetal` (since renamed `wakuwaku-provisioner`) already operates four live production WhatsApp numbers under the per-phone-number model, confirmed by an operational incident: an outdated GoWA binary was rejected by WhatsApp's servers (`Client outdated`, HTTP 405) starting 2026-05-18, taking down all four instances until the binary was updated to v9.0.0 and the units restarted. These numbers exist entirely outside WakuWaku's data model today (WakuWaku predates having any real users), so cutting over to the per-workspace model requires attributing each existing number to a workspace/owner, not just standing up new infrastructure.

## Goals / Non-Goals

**Goals:**
- Let a workspace add, link (QR or pairing code), check the status of, unlink, and delete WhatsApp numbers.
- Isolate tenants from each other at both the OS-process level and the API-credential level, despite GoWA itself having no tenant concept.
- Provision infrastructure lazily and only pay the resource cost of a GoWA instance when a workspace actually adds its first number.
- Define an explicit, idempotent, atomic API contract between WakuWaku and `waini-provisioner` that both repos can implement and test independently.
- Keep Kong lightweight (no Postgres dependency) while still allowing routes to be updated programmatically as workspaces are provisioned or removed.

**Non-Goals:**
- Chat, contact, or message management (a later phase).
- Multi-host or sharded deployment of GoWA/Kong (single bare-metal host only; see Risks).
- High-availability/failover of the `waini` host.
- Implementing `waini-provisioner` or the Kong DB-less migration itself — those live in the `waini-baremetal` repo and are out of this change's edit scope.
- Executing the cutover of the 4 confirmed existing production instances (see Context) to the per-workspace model. This change plans for it (see Risks, Migration Plan, and `tasks.md`) but the cutover work itself — owner attribution, sequencing, and running it — happens against the `wakuwaku-provisioner` repo, not as part of this change.

## Decisions

### D1: One GoWA process per workspace, not fully shared and not per-number

Each workspace that adds a WhatsApp number gets its own GoWA process (own port, own `storages/` — session + chat DB — own `APP_BASIC_AUTH`), managing every number that workspace adds via GoWA's own multi-device support.

**Alternatives considered:**
- *Fully shared single instance* (one GoWA process for all workspaces, tenants distinguished only by `device_id`): rejected — GoWA's single process-wide Basic Auth credential would mean every tenant's numbers share one API credential, and a crash/ban/memory issue on one tenant's traffic can affect every other tenant.
- *One process per phone number* (the current `waini-baremetal` model): rejected — wasteful once GoWA supports multi-device per process (a workspace with 5 numbers would cost 5 processes/ports/systemd units/Kong routes instead of 1), and doesn't use the capability GoWA v9 already provides.

Per-workspace isolation falls directly out of `waini-baremetal`'s existing shape: its systemd template (`whatsapp@.service`) and per-instance `storages/` directory already model "one process, one credential, one session store" — the only change is what `%i` identifies (workspace ID instead of phone number).

### D2: `waini-baremetal` stays a separate repository

Rejected folding it into `wakuwaku` (as a submodule or as tracked plain files). `wakuwaku` has direct precedent against submodules (commit `1d9d676`, moving `vitamind-plugin-sdk` to plain tracked files to reduce early-phase friction and remove a leaked PAT from a submodule remote URL) — but that precedent doesn't transfer cleanly here: `vitamind-plugin-sdk` is a PHP package `composer install`-ed into the same running Laravel process, while `waini-baremetal` runs on a separate bare-metal host and is only ever reached over the network. Its deploy topology is genuinely a different machine, so folding it into the app repo would mix two unrelated deployment lifecycles. Coordination between the two repos during active co-design is instead handled by an explicit, versioned API contract (this document + `specs/`) rather than shared git history.

### D3: Kong runs DB-less (declarative), not Postgres-backed

`waini-baremetal`'s committed `kong/kong.conf` currently sets `database = postgres`, and `scripts/kong-sync-routes.sh` does incremental `POST`/`PATCH` calls against `/services` and `/routes` — traditional mode, not DB-less as the operator originally intended. Kong DB-less mode's Admin API returns `405` for those same per-entity CRUD calls; the only way to change routing is `POST` of the complete declarative config to `/config`, which Kong applies atomically with no separate database process. Adopting this removes the Postgres dependency (lighter, and removes a process whose health Kong currently depends on) and matches the original "ringan" intent.

**Trade-off accepted:** every routing change must re-submit the *entire* desired state, not a diff. This is a good fit here because the desired state (all active workspace instances) is exactly what `waini-provisioner` already needs to track.

### D4: A small Go service (`waini-provisioner`), not a generic HTTP-to-shell bridge

Considered and rejected using `adnanh/webhook` (a generic HTTP-triggers-a-script tool) in front of adapted versions of `waini-baremetal`'s existing bash scripts. Rejected because: its success/failure model is binary (200/500), not precise REST semantics (409/422/503 etc.); argument passing from JSON body into shell script arguments is an injection-prone indirection; and generating `kong.yml` via bash string-templating is fragile for an operation that atomically replaces *all* tenants' routing at once — the blast radius of a malformed config is every workspace, not one. A purpose-built Go service gets typed request/response handling, an in-process mutex instead of `flock`, and typed YAML generation (`yaml.v3` over Go structs) instead of string templates.

`waini-provisioner` runs as its own systemd unit, binds to `127.0.0.1` only, and is reachable exclusively through Kong.

### D5: Two different auth models for two different route classes

- `/provisioner/*` (routes to `waini-provisioner`) is gated by Kong's `key-auth` + `ip-restriction` plugins, restricted to WakuWaku's static outbound IP. This is the sensitive control-plane surface (starts/stops OS processes, knows every workspace's credentials, rewrites global routing) and needs precise, auditable access control.
- `/w/{workspace_id}/*` (routes to a workspace's GoWA instance) carries no Kong-level auth plugin; it relies on the GoWA instance's own `APP_BASIC_AUTH`, passed through unmodified (`strip_path` only). WakuWaku authenticates directly against each instance using the credential returned when that instance was provisioned.

Reusing `vitamind-core`'s `ApiKey` feature (Sanctum `PersonalAccessToken`, user-scoped) for the WakuWaku→waini credential was considered and rejected: it authenticates *inbound* callers of WakuWaku's own API and is verified inside WakuWaku's own Laravel process/DB. `waini-provisioner` is not a Laravel app and has no access to that database; using it would require a callback into WakuWaku on every request, coupling the wrong direction.

### D6: `waini-provisioner`'s scope is instance lifecycle only

`waini-provisioner` exposes exactly four operations — create, get status, delete, health — and never proxies or has any awareness of individual WhatsApp numbers/devices. All device-level operations (add device, QR/pairing-code login, logout, status, purge) are called by WakuWaku **directly** against the workspace's own GoWA instance, using the `base_url` + Basic Auth credential returned by `POST /instances`. This is a deliberate boundary, not an oversight: it keeps the provisioner's blast radius and privilege minimal (it never needs to know what a "device" is), and avoids making it a bottleneck for the much higher-frequency device-level traffic.

### D7: Filesystem is the source of truth on the `waini` side

`waini-provisioner` keeps no separate database or registry. An instance's existence, port, and credentials are derived by scanning `wa_ws_*/.env` files at request time. This matches `waini-baremetal`'s existing `kong-sync-routes.sh` pattern (which already scans folders) and avoids introducing a new stateful component whose truth could drift from the actual systemd/filesystem state.

### D8: `POST /instances` is idempotent and atomic

Guarded by a single global in-process mutex (port allocation must see every existing instance, and provisioning is not a hot path, so a global lock is acceptable). The full flow: validate `workspace_id` (`^[a-z0-9][a-z0-9_-]{0,62}$` — one rule that keeps the identifier safe as a folder name, a systemd instance name, and a Kong path segment simultaneously) → check for an existing, healthy instance (return it as-is) or a stale one (attempt self-heal) → allocate a free port from a configured range → create the working directory → generate `APP_BASIC_AUTH` via `crypto/rand` → write `.env` → `systemctl enable --now` via `exec.Command` with an argv array (never a shell) → poll `/health` with a bounded timeout → regenerate the full Kong declarative config and `POST /config`. Failure at any step from directory creation onward rolls back everything created so far (stop/disable the unit, remove the directory) — including rolling back the instance if the Kong sync step itself fails, so the system is never left with a running-but-unrouted instance. This makes the operation safe for WakuWaku to retry on failure without manual cleanup.

### D9: Provisioning is lazy

A workspace's GoWA instance is created on demand, when the workspace's first WhatsApp number is added — not eagerly when the workspace itself is created. Since every user gets a default workspace automatically today (`ensureHasDefaultWorkspace()` in `vitamind-workspace-plugin`), eager provisioning would allocate a real process/port/systemd-unit/Kong-route for every signup regardless of whether they ever add a number.

### D10: "One workspace per user" is a WakuWaku application policy, not a package change

`vitamind-workspace-plugin` defaults to multi-workspace-per-user (`CreateWorkspace`, `InviteToWorkspace`, workspace switcher) and is intentionally left unchanged, since it is a shared, reusable package other VitaminD-based projects may depend on for genuine multi-workspace behavior. WakuWaku instead adds its own policy gate — intercepting workspace-creation and invite-acceptance flows at the application layer — to enforce the one-user-one-workspace product constraint. See the `single-workspace-tenancy` capability spec for the exact enforcement points.

## Risks / Trade-offs

- **Single bare-metal host is a capacity ceiling** (all instances currently bind to `localhost:<port>` on one machine) → Accepted for now; out of scope per Non-Goals. Revisit with a workspace→host routing table if/when a second host is needed.
- **Kong DB-less `/config` is a full-replace operation** → every sync re-submits all routes; mitigated by `waini-provisioner`'s global mutex serializing all provisioning/deprovisioning, so concurrent requests can't race on a stale read of the current state.
- **GoWA has no tenant isolation of its own** → mitigated entirely at the `waini` layer (D1/D5): process, port, storage, and credential are all per-workspace, so GoWA's own single-credential-per-process limitation becomes a tenant boundary rather than a leak.
- **Remote logout**: a user can unlink a device directly from their phone; GoWA reports this asynchronously (`DEVICE_LOGGED_OUT` over its WebSocket / webhook), not as a response to a WakuWaku-initiated call → WakuWaku's number-management flows must treat "unlinked" as an event it receives, not only a state it sets.
- **`waini-provisioner` needs privilege to manage systemd units** → scoped via a `sudoers` allowlist limited to `systemctl {start,stop,enable,disable} whatsapp@*`, not run as root and not (for this version) via `go-systemd`/D-Bus, which would need equivalent polkit configuration for a marginal robustness gain on a low-frequency operation.
- **Undecided deprovision retention policy** (see Open Questions) → risk of irreversible data loss if immediate purge is chosen without review; not decided by this design.
- **Two-repo coordination overhead** → mitigated by this document and `specs/` being the explicit, versioned contract both repos implement against, per D2.
- **Cutting over the 4 existing production instances risks disrupting already-linked numbers** — these numbers are live today and their owners are not yet represented in WakuWaku's data model at all → mitigate by attributing each existing number to its rightful workspace/owner *before* any infrastructure cutover (not after), and by preferring a gradual, session-preserving migration path over a single big-bang cutover that forces all four to re-pair at once. See `tasks.md` §9.

## Migration Plan

WakuWaku's own data model is greenfield (no existing WakuWaku user/workspace data depends on it), but the underlying infrastructure is not: 4 production WhatsApp numbers are already live under the old model (see Context). Sequencing:

1. `waini-baremetal`/`wakuwaku-provisioner` side (external to this change): migrate Kong to DB-less, repurpose the systemd template from per-number to per-workspace, implement `waini-provisioner` against the contract in `specs/whatsapp-workspace-provisioning/`. Verified independently in that repo.
2. WakuWaku side (this change): migrations for the per-workspace connection record and `WaNumber` model, the `single-workspace-tenancy` policy gate, the two outbound HTTP clients (provisioner + direct GoWA), and the inbound webhook receiver.
3. WakuWaku only points at a real `waini-provisioner` endpoint once step 1 is verified in the target environment; until then, number-management flows have nothing to provision against.
4. Production cutover (after 1–3 are verified): attribute each of the 4 existing numbers to a workspace/owner in WakuWaku, then migrate them off the old per-phone-number instances onto the new per-workspace ones. Not part of this change's tasks beyond planning — see `tasks.md` §9.

Rollback: WakuWaku's changes are additive (new tables, new routes, a new policy gate) and can be reverted with a standard migration rollback. The Kong DB-less migration on `waini`'s side is a configuration change (`kong.conf` + declarative config file) and can be reverted to the traditional/Postgres setup independently of WakuWaku. The production cutover (step 4) is the one step that isn't cleanly reversible once existing numbers are moved — see Risks.

## Open Questions

1. **Deprovision retention** — does `DELETE /instances/{workspace_id}` purge the instance's storage immediately, or rename/archive it for a grace period before permanent deletion? Not decided.
2. **Concrete port range** for `waini-provisioner`'s allocation (e.g. `3001-3999` was used only as an illustrative example throughout discussion). Not decided.
3. **Webhook URL/payload scheme** — the exact shape of the single shared WakuWaku endpoint that all workspace GoWA instances call back to (path structure, HMAC secret verification using GoWA's `webhook_secret`) needs to be finalized against GoWA's actual webhook payload format (`docs/webhook-payload.md` in the GoWA repo) before implementation.
4. **`waini-provisioner`'s own Kong `key-auth` credential** — bootstrap and rotation workflow (who generates it initially, how WakuWaku is given it, how it's rotated) is not yet defined.
5. **Cutover approach for the 4 confirmed existing production instances** (see Context) — resolved: they exist. Still open: whether migration is gradual (one number at a time) or big-bang (all four together), whether existing WhatsApp sessions can be carried over to a per-workspace instance or require re-pairing from scratch, and who owns/confirms the workspace attribution for each number before cutover.
6. **Multi-host scaling** — explicitly deferred (see Non-Goals), but the point at which single-host capacity needs revisiting is undefined.

## Implementation Addendum (2026-08-07)

Two findings surfaced during implementation of tasks.md §§1–6, resolved as follows:

**Open Question 3 (webhook scheme), partially resolved and partially closed as infeasible.** Verified directly against the upstream `aldinokemal/go-whatsapp-web-multidevice` v9 source: GoWA's device-lifecycle API is `POST/GET/DELETE /devices[/{device_id}]`, `GET /devices/{device_id}/login` (QR), `POST /devices/{device_id}/login/code?phone=` (pairing code), `GET /devices/{device_id}/status`, `POST /devices/{device_id}/logout` — all Basic-Auth protected, matching D1/D5. The webhook itself is `POST` to the URL registered at device-creation time, body `{"event", "device_id" (WhatsApp JID), "session_id" (the caller-chosen device_id from `POST /devices`), "payload"}`, signed via `X-Hub-Signature-256: sha256=<HMAC-SHA256 of the raw body>`. **However**, `src/infrastructure/whatsapp/event_handler.go`'s `handleLoggedOut()` only broadcasts `DEVICE_LOGGED_OUT` over GoWA's internal WebSocket — it never calls a `forwardXToWebhook()` function the way every other event type does. Remote/phone-initiated logout is therefore **not deliverable through the webhook GoWA currently exposes**. Decision (2026-08-07, user-confirmed): implement the generic webhook receiver (attribution + signature verification) for the events GoWA does forward, and leave task 6.4 / the "Remote unlink events are reflected without a WakuWaku-initiated action" requirement in `specs/whatsapp-number-management/spec.md` unimplemented rather than guess a delivery mechanism. Revisit via either status polling or an upstream GoWA change in a future change.

**Workspace onboarding race, discovered and fixed via upstream merge, not local workaround.** Implementing the single-workspace-tenancy gate surfaced a real bug in `vitamind-workspace-plugin`'s pre-existing default-workspace behavior: `ensureHasDefaultWorkspace()` ran as a side effect of Inertia's shared-prop resolution on *every* authenticated request (confirmed against `inertiajs/inertia-laravel`'s `Middleware::handle()` — `share()` runs before `$next($request)`), including the invite-accept request itself, making invite acceptance permanently unreachable once any "already has a workspace" gate was added. The user pointed to a fix already prepared on `origin/dev` (archived as `openspec/changes/archive/2026-08-06-fix-workspace-invite-onboarding`), which replaces silent auto-creation with an explicit `EnsureWorkspaceOnboarded` middleware + `workspaces.onboarding` choice screen (create or accept, first-membership-only). That branch was merged into `master` as part of this implementation. `single-workspace-tenancy/spec.md`'s "New users still receive their one default workspace automatically" requirement is superseded by this: new users are now routed to an explicit onboarding choice rather than silently defaulted — the higher-level guarantee (exactly one workspace per user, no silent drift) still holds, just via a different, more correct mechanism. The gate itself (`App\Http\Middleware\PreventMultipleWorkspaces`, `Gate::before` in `AppServiceProvider`) now guards three entry points: `workspaces.store`, `workspaces.invitations.accept` (signed-link click-through), and `workspaces.onboarding.accept` (onboarding screen).

## Implementation Addendum 2 (2026-08-07): `waini-provisioner` is real, checked out, and does not use Kong

An earlier version of this addendum (and this document's D2–D5) assumed `waini-provisioner` was unimplemented and unreachable for verification. Both were wrong. It lives at `/home/wurin7i/DevSpace/wakuwaku-provisioner` (a working directory available in this same environment, registered as OpenSpec store `wakuwaku-provision`), is its own fully-specced OpenSpec change (`provision-workspace-whatsapp-instances`, 38/42 tasks done, `go build`/`go vet`/`go test` all passing), and **Kong has been fully retired there** — Caddy is the sole edge component (that repo's design.md D3/D4). This changes several things D3–D5 above describe:

- **Auth is HTTP Basic Auth via Caddy's `basicauth` directive**, not Kong key-auth. `App\Support\WhatsApp\ProvisionerHttp` was corrected to `withBasicAuth()`; `config('services.waini_provisioner')` now holds `username`/`password`, not a bearer `key`.
- **Response field names differ from this document's earlier assumption**, verified against `internal/provisioner/server.go`'s actual `instanceResponse` struct: `{"workspace_id", "status", "base_url", "port", "basic_auth_username", "basic_auth_password"}` — flat fields, `status` not `state`, no nested `basic_auth` object. `App\Actions\Provisioner\CreateInstance`/`GetInstanceStatus` were corrected accordingly.
- **`GET /instances/{id}` returns HTTP 200 with `"status":"absent"` for a nonexistent instance, not a 404.** `GetInstanceStatus` was corrected — it no longer branches on HTTP 404.
- **There is no `webhook_secret` in the create-instance response, and the provisioner never configures GoWA's `WHATSAPP_WEBHOOK_SECRET`.** WakuWaku now generates and owns this secret itself (`CreateInstance`, `Str::random(40)`, preserved across idempotent re-calls), passing it per-device via `AddDevice`'s optional `webhook_secret` body field (confirmed present in GoWA's `POST /devices`).
- On live infrastructure (confirmed via SSH to the actual `clients-lab`/`wagw.nugrahadi.com` host, 2026-08-07): Kong is installed but its systemd unit is `inactive (dead)` — production traffic doesn't go through it today either; nginx proxies each legacy phone number directly by path. So there was never a live Kong to migrate away from on that host, in addition to the code-level retirement in `wakuwaku-provisioner`.

A full deployment/cutover runbook for standing this up on `clients-lab` alongside the untouched legacy stack — including a required small code change to decouple Caddy's own listen address from the public instance URL (needed because nginx, not Caddy, must keep owning ports 80/443) — is at `wakuwaku-provisioner/DEPLOYMENT_PLAN.md`, not duplicated here.
