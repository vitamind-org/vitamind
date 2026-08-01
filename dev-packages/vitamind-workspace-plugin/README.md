# VitaminD Workspace Plugin

Optional multi-tenancy workspace/project system plugin for VitaminD Core. This plugin provides workspace management for applications that need multi-tenant capabilities.

## Installation

```bash
composer require vitamind/workspace-plugin
```

## Configuration

Enable the workspace plugin in your `.env`:

```
VITAMIND_FEATURE_WORKSPACES=true
```

## Database Migrations

Run migrations to set up workspace tables:

```bash
php artisan migrate
```

## Features

- **Workspace Management**: Create and manage multiple workspaces
- **User Workspace Assignment**: Assign users to workspaces with role-based access
- **Workspace Isolation**: Middleware for workspace-aware request handling
- **Feature Flag Gating**: Workspace is fully optional and can be disabled

## Usage

Once enabled and migrated, the workspace plugin provides:

- Workspace model and related relationships
- Workspace controllers and API endpoints
- Workspace middleware for route protection
- Database tables for workspace data

## Disabling Workspace

To disable the workspace plugin, set in `.env`:

```
VITAMIND_FEATURE_WORKSPACES=false
```

This will prevent workspace tables from being created and workspace routes from being registered.

## Documentation

For detailed documentation, see [VitaminD Documentation](https://github.com/vitamind-org).

## License

MIT
