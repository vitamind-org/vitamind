## 1. Plugin SDK: new contract and shared behavior

- [x] 1.1 Add `VitaminD\PluginSdk\Interfaces\HasPluginDetails` (`pluginDetails(): array{key: string, name: string, description: string}`)
- [x] 1.2 Update `VitaminD\PluginSdk\Interfaces\PluginInterface`: `extends HasPluginDetails`, remove `getName(): string` and `getDescription(): string`
- [x] 1.3 Add `VitaminD\PluginSdk\Concerns\RegistersOwnRole` trait: `registerRole(string $shortKey, string $title)` and `pluginKey()` (throws `LogicException` when `pluginDetails()['key']` is missing/empty)

## 2. Plugin SDK: base classes

- [x] 2.1 Update `AbstractPlugin`: remove `$name`, `$description`, `getName()`, `getDescription()`; add `use RegistersOwnRole;` and `abstract public function pluginDetails(): array;`
- [x] 2.2 Add `VitaminD\PluginSdk\PluginBase` (`abstract class PluginBase extends ServiceProvider implements HasPluginDetails`, `use RegistersOwnRole;`, `abstract public function pluginDetails(): array;`)

## 3. Plugin SDK: `RegisterRole` breaking change

- [x] 3.1 Change `RegisterRole::register()` to `register(string $pluginKey): void`, setting `$this->key = $pluginKey.'.'.$this->shortKey`
- [x] 3.2 Remove `prefixFromClass()`, the `debug_backtrace()` call, and the now-unused `Illuminate\Support\Str` import from `RegisterRole.php`
- [x] 3.3 Update the class docblock to describe explicit-key registration instead of namespace-guessing

## 4. Core: read identity from `pluginDetails()`

- [x] 4.1 Update `vitamind-core`'s `Actions/Plugins/InstallPlugin.php`: replace `$implementation->getName()`/`getDescription()` with `$implementation->pluginDetails()['name']`/`['description']`
- [x] 4.2 Update `vitamind-core`'s `Actions/Plugins/EnablePlugin.php`: same replacement

## 5. Migrate existing plugins to the new contract

- [x] 5.1 `WorkspaceServiceProvider`: `extends PluginBase`, add `pluginDetails()` returning `['key' => 'workspace', 'name' => 'Workspace Plugin', 'description' => 'Optional multi-tenancy workspace/project system for VitaminD Core.']`
- [x] 5.2 `ArchiveServiceProvider`: `extends PluginBase`, add `pluginDetails()` returning `['key' => 'archive', 'name' => 'Archive Plugin', 'description' => 'Folder and file storage with per-item visibility control.']`
- [x] 5.3 `RealtimeServiceProvider`: `extends PluginBase`, add `pluginDetails()` returning `['key' => 'realtime', 'name' => 'Realtime Plugin', 'description' => 'Optional WebSocket broadcasting (Laravel Reverb) infrastructure for VitaminD Core.']`
- [x] 5.4 `vitamind-todo-plugin`'s `src/Plugin.php`: add `pluginDetails()` returning `['key' => 'todo-plugin', 'name' => 'Todo Plugin', 'description' => 'Demo plugin used to verify GitHub and Composer plugin installation.']`; replace `RegisterRole::make('manager')->title('Todo Manager')->register()` with `$this->registerRole('manager', 'Todo Manager')` (resulting key stays `todo-plugin.manager` — no change needed to `Todo::MANAGER_ROLE`)
- [x] 5.5 `app/Plugins/HelloWorld/Plugin.php`: replace `$name`/`$description` properties with `pluginDetails()` returning `['key' => 'hello-world', 'name' => 'Hello World', 'description' => 'A very simple HelloWorld demo local plugin for VitaminD.']`

## 6. Tests

- [x] 6.1 Rewrite `dev-packages/vitamind-plugin-sdk/tests/AbstractPluginTest.php`: every anonymous `extends AbstractPlugin {}` gains `pluginDetails()`; replace `getName()`/`getDescription()` assertions with `pluginDetails()` assertions
- [x] 6.2 Add `dev-packages/vitamind-plugin-sdk/tests/PluginBaseTest.php` mirroring `AbstractPluginTest`: anonymous `extends PluginBase`, assert `pluginDetails()`, assert `registerRole()`/`pluginKey()` work via the shared trait
- [x] 6.3 Rewrite `dev-packages/vitamind-plugin-sdk/tests/RegisterRoleTest.php`: remove namespace-guessing-based tests, add tests for explicit-key registration (`register('acme')` → `acme.sales`), two different plugin keys not colliding, same short key under two different plugin keys not colliding
- [x] 6.4 Delete `dev-packages/vitamind-plugin-sdk/tests/Fixtures/Plugins/PackageA/RoleRegistrar.php` and `PackageB/RoleRegistrar.php` (existed only to simulate distinct caller namespaces — no longer applicable)
- [x] 6.5 Add a test asserting `RegistersOwnRole::pluginKey()` throws `LogicException` when `pluginDetails()` omits or empties `key`

## 7. Documentation

- [x] 7.1 Rewrite `docs/plugin-development/role-registration.md`'s "Automatic key prefixing" section (and the "Worked example" section) to describe `pluginDetails()`/`registerRole()` as the identity mechanism, removing references to namespace-guessing as the primary path

## 8. Verification

- [x] 8.1 `php artisan test --filter="RegisterRole|AbstractPlugin|PluginBase"` passes
- [x] 8.2 `php artisan test` (full suite) passes with no regressions
- [x] 8.3 `php -l` on every changed/new file
- [x] 8.4 `vendor/bin/pint --dirty`
