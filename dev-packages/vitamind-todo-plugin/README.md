# VitaminD Todo Plugin (Demo)

A minimal demo plugin for VitaminD. It exists to verify the GitHub and Composer
plugin installation pipelines end-to-end (tasks 12.10-12.12 of
`phase1-standalone-boilerplate`), not as a real feature. It registers a single
"Todo Plugin" admin page backed by a `todos` table.

## Installation

Via GitHub (clones into `storage/plugins/`):

```bash
php artisan plugin:install-github vitamind-org/todo-plugin
```

Via Composer (installs into `vendor/vitamind/todo-plugin`):

```bash
composer require vitamind/todo-plugin
```

## License

MIT
