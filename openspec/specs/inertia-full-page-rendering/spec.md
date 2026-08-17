# inertia-full-page-rendering Specification

## Purpose

Governs how the host app renders a full (non-XHR) Inertia page response — specifically, that the response must stay within the header-size limits reverse proxies (e.g. nginx/fastcgi) impose on upstream responses, for any page regardless of how many Vite-built chunks it depends on.

## Requirements

### Requirement: Full-page Inertia responses SHALL NOT emit unbounded resource-preload headers

When rendering a full (non-XHR) Inertia page response, the application SHALL NOT attach a response header whose size grows with the number of Vite-built asset chunks the requested page (and its shared entry point) depends on. Any HTTP response header added to a full-page render SHALL have a size that is independent of the page's chunk count, so that no page — however many chunks it imports — can cause the response's headers to exceed limits imposed by upstream reverse proxies (e.g. nginx's fastcgi header buffer).

#### Scenario: Refreshing a menu page does not exceed reverse-proxy header limits
- **WHEN** an authenticated user refreshes the browser (full page load, not client-side navigation) on an Inertia page whose component graph resolves to many Vite chunks (e.g. a settings or admin page with data tables, dialogs, and forms)
- **THEN** the server's HTTP response headers stay within the size the reverse proxy in front of the application accepts
- **AND** the reverse proxy relays the response to the browser instead of returning a gateway error

#### Scenario: Directly navigating to a deep route renders successfully
- **WHEN** an authenticated user types a page's URL directly into the address bar (a fresh, full page load with no prior client-side Inertia session in that tab)
- **THEN** the page renders successfully (HTTP 200) with the same content the user would see by reaching that route through in-app navigation
- **AND** no gateway error (e.g. HTTP 502) occurs regardless of how many components/chunks that page depends on

#### Scenario: Client-side Inertia navigation is unaffected
- **WHEN** an authenticated user navigates between pages by clicking links within the already-loaded application (client-side Inertia navigation, `X-Inertia: true`)
- **THEN** the response continues to be the JSON page payload with no added preload header, unchanged from current behavior

#### Scenario: Adding more plugins/pages does not reintroduce the failure
- **WHEN** additional plugins are installed that ship additional Inertia pages and Vite chunks, increasing the total size of the application's shared and page-specific chunk graphs over time
- **THEN** full-page render response headers remain unaffected by this growth
- **AND** no reverse-proxy header-size failure is introduced by the growth in chunk count alone
