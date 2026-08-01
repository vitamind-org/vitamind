# Vitamin-D

Vitamin-D is a modern, enterprise-ready, metadata-driven Single Page Application (SPA) portal framework built on top of Laravel 13, Inertia.js v3, React 19, Tailwind CSS v4, and TypeScript. 

It provides a highly decoupled, plugin-based architecture where dashboard pages, forms, and data tables can be fully defined in backend PHP code and dynamically rendered using typed React components on the frontend.

> [!NOTE]  
> **Inspiration & Acknowledgement:** Vitamin-D is heavily inspired by and adapted from the brilliant architecture of [VitoDeploy](https://github.com/vitodeploy/vito). We have extracted, generalized, and decoupled many of their robust patterns to create this generic, metadata-driven SPA solution.

---

## 🚀 Key Advantages & Feature

Vitamin-D is designed to offer the developer velocity of rapid admin panel builders  combined with the rich user experience and flexibility of a modern React SPA.

### 1. Modern SPA Tech Stack
* **React 19 + Inertia.js v3 + TypeScript:** Benefit from strict static typing, reusable frontend components, and smooth client-side transitions (no full-page reloads).
* **Tailwind CSS v4:** Leverages the latest CSS-first Tailwind compiler with CSS variables, cascading layers, and optimized utility compilation.
* **Next-Themes Integration:** Seamless out-of-the-box light/dark/system theme synchronization on the frontend.

### 2. Metadata-Driven Dynamic UI Engine
* Define complete admin interfaces—including multi-tab setups, complex forms, search/sort filters, and custom column formatters—entirely in PHP using the custom **Plugin SDK**.
* Layout configs are serialized to JSON and sent to a single React dynamic page (`resources/js/pages/plugins/dynamic-page.tsx`), which automatically renders the corresponding form fields and DataTables.
* Drastically reduces boilerplate: you write the schema once in PHP, and the React frontend handles the rest.

### 3. Clean & Maintainable Architecture
* **Action Pattern:** All business logic is separated from HTTP requests. Thin controllers handle requests/responses and delegate execution to reusable service actions (`VitaminD\Core\Actions\*`).
* **Spatie Route Attributes:** Keep routes clean and close to the execution context. Routes are declared directly on controllers using PHP 8 attributes instead of sprawling `web.php` or `api.php` files.
* **Abstract Models:** Base model inheritance (`VitaminD\Core\Models\AbstractModel`) ensures consistency in timestamps and shared query helpers.

### 4. Modular Plugin Architecture
* Build self-contained local features under `app/Plugins/{PluginName}/` — no `src/` subfolder needed, since it's application code, not a distributed package.
* Install community/1st-party plugins from GitHub (`storage/plugins/`) or Composer (`vendor/vitamind/*`) — see [`docs/local-plugins.md`](docs/local-plugins.md) for the full folder conventions and when to graduate a local plugin into a real package.
* Each plugin can register its own migrations, custom database schemas, navigation items, tabs, forms, and custom business logic in a single directory.

---

## 🛠️ Architecture vs. Standard FilamentPHP

While FilamentPHP is the go-to standard for Laravel admin panels utilizing Livewire and server-rendered HTML fragments, Vitamin-D takes a fundamentally different route by using a modern Inertia React SPA stack. This enables dynamic client-side rendering, type-safety with TypeScript, and a highly interactive, decoupled frontend.

Here is a high-level comparison of the architectural approaches:

| Feature | FilamentPHP | Vitamin-D |
| :--- | :--- | :--- |
| **Frontend Framework** | Blade + Alpine.js | React 19 + TypeScript |
| **Server Connection** | Livewire (Server-side HTML diffs) | Inertia.js (JSON API / Client SPA state) |
| **Styling Engine** | Tailwind CSS v3 | Tailwind CSS v4 |
| **Routing** | Web Routes / Resource classes | Controller Route Attributes |
| **Business Logic** | Embedded in Livewire/Resources | Clean Actions (`app/Actions`) |

---

## ⚙️ Getting Started

### Prerequisites
* PHP >= 8.2
* Node.js >= 20.x
* Composer
* NPM

### Local Installation

1. **Clone and Navigate:**
   ```bash
   cd vitamin-d
   ```

2. **Install Composer Dependencies:**
   ```bash
   composer install
   ```

3. **Install NPM Dependencies:**
   ```bash
   npm install
   ```

4. **Environment Setup:**
   Copy the example environment file and configure your database settings:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Run Migrations & Seeders:**
   ```bash
   php artisan migrate --seed
   ```

6. **Start Development Servers:**
   Run the Vite development server:
   ```bash
   npm run dev
   ```
   In a separate terminal, serve the Laravel application:
   ```bash
   php artisan serve
   ```

7. **Verify Types:**
   To ensure all TypeScript code compiles successfully:
   ```bash
   npm run types
   ```

---

## 📁 Key Directory Structure

* 📂 **`app/Plugins/`** — Local plugin code (`app/Plugins/{Name}/Plugin.php`, no `src/`).
* 📂 **`app/Models/`, `app/Providers/`** — Boilerplate-specific code that composes the packages below (e.g. `App\Models\User extends VitaminD\Core\Models\User`).
* 📂 **`dev-packages/`** — Development home for the extracted packages (see below). Wired into `vendor/vitamind/*` via Composer path repositories, so `composer install` is all that's needed — this isn't a separate setup step.
* 📂 **`resources/js/pages/`** — React page components rendered via Inertia.
* 📂 **`resources/js/components/ui/`** — Reusable, generic UI components (Buttons, Inputs, Dialogs, DataTables).
* 📄 **`config/vitamin-d.php`** — Feature flags and configuration settings.

---

## 📦 Phase 1: Extracted Packages

Vitamin-D's core functionality has been extracted into standalone Composer packages, so it can be required into a fresh Laravel project without pulling in this whole boilerplate:

| Package | Purpose | Source (dev) |
| :--- | :--- | :--- |
| `vitamind/core` | User management, auth (Fortify/Sanctum), admin panel infra, plugin engine, REST API | `dev-packages/vitamind-core/` |
| `vitamind/workspace-plugin` | Optional multi-tenancy (workspaces), opt-in via `VITAMIND_FEATURE_WORKSPACES` | `dev-packages/vitamind-workspace-plugin/` |
| `vitamind/plugin-sdk` | Contracts, DTOs, and registries used by all plugins | `dev-packages/vitamind-plugin-sdk/` |

This boilerplate is now a **starter kit** that requires `vitamind/core` (plus the optional workspace plugin) rather than containing that logic directly. During development, the packages live in `dev-packages/` and are symlinked into `vendor/vitamind/*` via a Composer path repository; in production they're installed like any other Packagist dependency.

See:
- [`docs/local-plugins.md`](docs/local-plugins.md) — local vs. GitHub vs. Composer plugin folder conventions
- [`MIGRATION_GUIDE.md`](MIGRATION_GUIDE.md) — installing `vitamind/core` in a fresh Laravel project, or upgrading an existing pre-Phase-1 project
- [`CONTRIBUTING.md`](CONTRIBUTING.md) — building plugins for Vitamin-D
