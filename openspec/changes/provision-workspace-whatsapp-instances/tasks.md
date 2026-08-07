## 1. Data model & migrations (WakuWaku)

- [x] 1.1 Migration + model for a workspace's GoWA connection record (workspace reference, base URL, encrypted Basic Auth credential, state, timestamps)
- [x] 1.2 Migration for `wa_numbers` (or equivalent), scoped with `dev-packages/vitamind-plugin-sdk`'s `BelongsToWorkspace` trait, following the same pattern as `Todo` in `vitamind-todo-plugin`
- [x] 1.3 `WaNumber` model with device state, GoWA `device_id`, and workspace scoping

## 2. Single-workspace-tenancy policy gate (WakuWaku)

- [x] 2.1 Identify interception points in the workspace-creation flow (`vitamind-workspace-plugin`'s `CreateWorkspace` action call site) and invite-acceptance flow (`AcceptWorkspaceInviteController`, plus `WorkspaceOnboardingController::accept()` added by the `origin/dev` onboarding-flow merge — see design.md addendum)
- [x] 2.2 Add an application-level check rejecting workspace creation when the acting user already belongs to a workspace
- [x] 2.3 Add an application-level check rejecting invite acceptance when the acting user already belongs to a workspace
- [x] 2.4 Update relevant UI affordances (hide/disable "create workspace" and pending-invite acceptance where blocked) to match the gate
- [x] 2.5 Tests: creation rejected, invite-acceptance rejected (both entry points), new users routed to onboarding rather than auto-assigned (see design.md addendum), `vitamind-workspace-plugin`'s own tests updated for the one intentionally-superseded case and otherwise unaffected

## 3. Provisioner client (WakuWaku)

- [x] 3.1 HTTP client configuration for the `waini-provisioner` API (base URL, bearer credential)
- [x] 3.2 Create-instance call, persisting the returned base URL, Basic Auth credential, and state into the connection record from 1.1
- [x] 3.3 Get-instance-status call
- [x] 3.4 Delete-instance call
- [x] 3.5 Error handling: safe-to-retry behavior on create (idempotent per `specs/whatsapp-workspace-provisioning/spec.md`), explicit failure surfacing otherwise

## 4. Direct GoWA client (WakuWaku)

- [x] 4.1 HTTP client per workspace, built from that workspace's stored connection record (base URL + Basic Auth)
- [x] 4.2 Add-device operation
- [x] 4.3 QR-login operation
- [x] 4.4 Pairing-code-login operation
- [x] 4.5 Device-status operation
- [x] 4.6 Logout (unlink, keep slot) operation
- [x] 4.7 Delete-device (purge) operation

## 5. Number management flows (WakuWaku)

- [x] 5.1 "Add number" flow: ensure the workspace's instance exists (trigger lazy provisioning via 3.2 if this is the first number), then create the device via 4.2, then persist the `WaNumber`
- [x] 5.2 QR linking UI/endpoint using 4.3
- [x] 5.3 Pairing-code linking UI/endpoint using 4.4
- [x] 5.4 Number status display using 4.5
- [x] 5.5 Unlink action using 4.6
- [x] 5.6 Delete-number action using 4.7
- [x] 5.7 Tests verifying numbers are only visible/reachable within their owning workspace

## 6. Webhook ingestion (WakuWaku)

- [x] 6.1 Single inbound webhook route/controller for GoWA events
- [x] 6.2 Payload signature verification using each instance's configured webhook secret
- [x] 6.3 Event attribution to the correct workspace and `WaNumber` from the payload's device/workspace identifier
- [ ] 6.4 Handle the remote-logout event, updating the corresponding `WaNumber`'s status without a WakuWaku-initiated action — **not implementable as specified**: verified directly against GoWA v9's source (`src/infrastructure/whatsapp/event_handler.go`) that `handleLoggedOut()` only broadcasts over GoWA's internal WebSocket, never forwards to the configured HTTP webhook the way every other event type does. Left unimplemented per explicit decision (2026-08-07); see design.md addendum for options considered.

## 7. wakuwaku-provisioner side (separate repo, `/home/wurin7i/DevSpace/wakuwaku-provisioner` — implemented there, not here; status verified 2026-08-07)

This repo's own OpenSpec change of the same name is 38/42 tasks done, `go build`/`go vet`/`go test` all passing. Kong was retired at the code level (Caddy is the sole edge component) rather than migrated to DB-less mode.

- [x] 7.1 ~~Migrate Kong from traditional (Postgres-backed) to DB-less declarative mode~~ — superseded: Kong retired entirely, not migrated to DB-less. Also confirmed moot on the actual production host: Kong's systemd unit there is `inactive (dead)`, nginx already routes directly to each GoWA process without it.
- [x] 7.2 Repurpose the `whatsapp@.service` template and instance folder layout from per-phone-number to per-workspace — done (`%i` now dual-purpose: phone number for legacy, workspace id for managed instances; both share the `wa_<id>` directory convention)
- [x] 7.3 Implement `waini-provisioner` (Go) against `specs/whatsapp-workspace-provisioning/spec.md` — done (`internal/provisioner/*`, `cmd/waini-provisioner/main.go`); response field names differ from this change's original assumption, corrected in §3 above (see design.md Implementation Addendum 2)
- [x] 7.4 ~~Configure Kong `key-auth` + `ip-restriction` plugins fronting `/provisioner/*`~~ — superseded: Caddy's `basicauth` directive + `remote_ip` matcher achieves the same access control, no Kong involved
- [x] 7.5 Configure a scoped `sudoers` rule for `waini-provisioner`'s `systemctl` access — done (`scripts/sudoers/waini-provisioner`, scoped to `enable --now`/`stop`/`disable` on `whatsapp@*` only)
- [x] 7.6 Resolve `design.md`'s open questions before implementation — resolved in that repo's own design.md: deprovision is immediate purge (no grace period), port range 4001-4999, sudoers scoping done. Provisioner credential bootstrap/rotation remains manual (not automated). Existing per-phone-number deployment status confirmed directly via SSH (see §9).
- [ ] 7.7 (new) Deploy the above to `clients-lab`/`wagw.nugrahadi.com` — not yet done; full runbook at `wakuwaku-provisioner/DEPLOYMENT_PLAN.md`, including a small required code change (decouple Caddy's listen address from the public instance URL, since nginx — not Caddy — must keep owning ports 80/443 on that shared host)

## 8. Cross-cutting verification

- [ ] 8.1 End-to-end test: add the first WhatsApp number in a fresh workspace through to linked status, against a real or staging `waini-provisioner` + GoWA instance — blocked on deployment (§7.7), not on the provisioner being unimplemented (it isn't — see §7). Target environment: `clients-lab`, per `wakuwaku-provisioner/DEPLOYMENT_PLAN.md` Phase 5-6.
- [x] 8.2 Verify tenant isolation: two workspaces' instances, credentials, and numbers never cross — covered by `tests/Feature/WaNumberManagementTest.php` and `tests/Feature/WhatsAppWebhookTest.php` at the WakuWaku-side logic level (mocked HTTP); not yet exercised against real infrastructure
- [ ] 8.3 Verify create-instance idempotency and rollback-on-failure behavior against the running provisioner — blocked on deployment (§7.7); WakuWaku-side idempotent-caller behavior is covered by `tests/Feature/ProvisionerClientTest.php`, and the provisioner's own idempotency/rollback logic has its own passing Go test suite (`internal/provisioner/manager_test.go`) in the other repo

## 9. Production cutover of existing instances

Not greenfield: 4 WhatsApp numbers are already live (confirmed via commit `ca88ae2`, 2026-08-02, and directly via SSH 2026-08-07 — `whatsapp@62811286925`, `whatsapp@6285335997796`, `whatsapp@6285701176008`, `whatsapp@628980852000`, all `active (running)` on `clients-lab`/`wagw.nugrahadi.com`, routed by nginx directly, no Kong in the live path). Sequencing decided by the operator (2026-08-07): **gradual, one number at a time**; `wagw.nugrahadi.com` and its legacy per-phone-number path are left running exactly as-is throughout — a separate new deployment (`wakuwaku.nugrahadi.com`, DNS pending) is built alongside it, not in place of it. Full runbook: `wakuwaku-provisioner/DEPLOYMENT_PLAN.md` Phase 7.

- [ ] 9.1 Identify the current owner for each of the 4 existing numbers and create their corresponding workspace + `WaNumber` records in WakuWaku, before any infrastructure cutover
- [x] 9.2 Decide and document the cutover sequencing — decided (see above): gradual, session carry-over not assumed safe without verification (re-pairing is the default per number unless proven otherwise)
- [ ] 9.3 Execute cutover for each of the 4 numbers per the agreed sequencing, confirming no downtime beyond what's communicated to affected owners
- [ ] 9.4 Decommission the old per-phone-number `whatsapp@*.service` instances only after all 4 are confirmed migrated and stable on the new model
