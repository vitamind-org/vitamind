# VitaminD Core

The core VitaminD package containing user management, authentication, admin infrastructure, and the plugin engine for Laravel applications.

## Installation

```bash
composer require vitamind/core
```

## Features

- **User Management**: Complete user authentication system with Laravel Fortify and Sanctum
- **Admin Panel Infrastructure**: Building blocks for admin UI
- **Plugin Engine**: Core infrastructure for discovering and managing plugins
- **REST API Support**: Sanctum-based API authentication
- **TypeScript Support**: Automatic DTO to TypeScript generation

## Configuration

Publish the configuration:

```bash
php artisan vendor:publish --provider="VitaminD\Core\Providers\CoreServiceProvider"
```

## Database Migrations

Run migrations to set up core tables:

```bash
php artisan migrate
```

## Usage

The core service provider is automatically registered and bootstraps:

1. Plugin discovery and boot sequence
2. Sanctum integration with PersonalAccessToken
3. JSON resource wrapping configuration
4. Console commands registration

## Documentation

For detailed documentation, see [VitaminD Documentation](https://github.com/vitamind-org).

## License

MIT
