# plugin-workspace-scoping Specification

## Purpose

Plugin workspace scoping lets any plugin opt its own Eloquent models into per-workspace data isolation by applying a single trait (`VitaminD\PluginSdk\Concerns\BelongsToWorkspace`) and adding a nullable `workspace_id` column — with no changes to the plugin's page, table, or column registrations. The scoping is relaxed rather than a hard dependency: it behaves as a global, unscoped model whenever the workspaces feature is disabled or there is no authenticated workspace context, and the SDK trait itself never depends on the workspace plugin package. Scoped columns are also excluded from mass assignment so requests cannot smuggle a record into another workspace.

## Requirements

### Requirement: Plugin models can opt into workspace scoping via a single trait

A plugin SHALL be able to scope its own data to the acting user's current workspace by applying one trait, `VitaminD\PluginSdk\Concerns\BelongsToWorkspace`, to its Eloquent model and adding a nullable `workspace_id` column. Reads SHALL be filtered to the current workspace and new records SHALL be stamped with it automatically, with no changes required to the plugin's page, table, or column registrations.

#### Scenario: Reads are limited to the current workspace
- **WHEN** a user with a current workspace queries a model using the trait
- **THEN** only records belonging to that workspace are returned
- **AND** records belonging to other workspaces are absent

#### Scenario: New records are stamped with the current workspace
- **WHEN** a user with a current workspace creates a record on a model using the trait
- **THEN** the record's `workspace_id` is set to that workspace without the plugin passing it explicitly

#### Scenario: Records from another workspace cannot be fetched by id
- **WHEN** a user attempts to fetch, update, or delete a record belonging to a different workspace by its id
- **THEN** the lookup returns nothing, so the generic plugin CRUD controller cannot act on it
- **AND** the failure is a not-found result, not merely omission from a listing

#### Scenario: Cross-workspace reads remain possible deliberately
- **WHEN** code needs to read across workspaces (reporting, admin tooling)
- **THEN** removing the `workspace` global scope on the query returns records from all workspaces

### Requirement: Workspace scoping is relaxed, not a gate on installation

Applying workspace scoping SHALL NOT make a plugin dependent on the workspaces feature. A plugin that scopes its data SHALL still install, enable, and function on a single-tenant project where the workspaces feature is disabled and the workspace plugin is absent.

#### Scenario: Scoped plugin behaves globally on a single-tenant install
- **WHEN** the `vitamin-d.features.workspaces` flag is disabled
- **THEN** the trait performs no filtering and stamps no `workspace_id`
- **AND** the model behaves exactly as an unscoped global model

#### Scenario: Unauthenticated contexts are unscoped rather than empty
- **WHEN** a model using the trait is queried with no authenticated user (console command, queued job), or by a user with no current workspace
- **THEN** no workspace filter is applied and all records are visible
- **AND** the query does not silently return an empty result

### Requirement: The SDK stays free of any workspace plugin dependency

The scoping trait SHALL reference no class from `vitamind/workspace-plugin`, depending only on the `vitamin-d.features.workspaces` config key, the authenticated user's `current_workspace_id` attribute, and the model's own `workspace_id` column. `vitamind/plugin-sdk` SHALL NOT require `vitamind/workspace-plugin`.

#### Scenario: SDK requires no workspace package
- **WHEN** `vitamind/plugin-sdk`'s dependencies are examined
- **THEN** `vitamind/workspace-plugin` is not among them
- **AND** a plugin can use the scoping trait without installing the workspace plugin

#### Scenario: Workspace column carries no foreign key
- **WHEN** a plugin adds its `workspace_id` column
- **THEN** the column is nullable and unconstrained, so the migration succeeds on a single-tenant install with no `workspaces` table

### Requirement: Workspace-scoped columns are not mass-assignable

A plugin's `workspace_id` column SHALL be excluded from the model's mass-assignable attributes, so a crafted request body cannot place a record into another workspace through the generic plugin CRUD endpoints.

#### Scenario: Request body cannot choose a workspace
- **WHEN** a create request includes a `workspace_id` value for a different workspace
- **THEN** the value is ignored and the record is stamped with the acting user's current workspace
