## ADDED Requirements

### Requirement: The plugin registers its own main-sidebar entry
The plugin SHALL register an "Archive" entry in the application's main sidebar via `VitaminD\PluginSdk\RegisterPage` in custom-link mode, pointing at its own `archive.index` route. This registration SHALL be the plugin's only means of appearing in the sidebar — the plugin SHALL NOT require any change to `resources/js/components/app-sidebar.tsx` or other host boilerplate to do so.

#### Scenario: Archive appears in the sidebar for a non-admin user
- **WHEN** the plugin is installed and enabled
- **AND** the current user is authenticated (not necessarily an admin)
- **THEN** an "Archive" entry appears in the main sidebar
- **AND** navigating it loads the plugin's own `archive.index` route (`FolderController::index`), rendering `@plugin/archive-plugin/index`, not the generic `dynamic-page` CRUD screen

#### Scenario: Registration uses custom-link mode, not tabs mode
- **WHEN** the plugin's service provider registers the "Archive" page
- **THEN** it calls `RegisterPage::make(...)->route('archive.index')->register()`
- **AND** it does not call `->tabs(...)`, since the folder/file hierarchy has no `RegisterDataTable`-representable shape
