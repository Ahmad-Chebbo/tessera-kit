# Tessera Engine

The module engine of [Tessera](https://github.com/Tessera-kit/tessera-kit). It finds the modules of a Laravel project, puts them in dependency order, boots the enabled ones and gives you the commands to make, check, package and install modules. It has no web pages and no database tables of its own, so any Laravel 13 application can use it, with or without Inertia.

## Install

```bash
composer config repositories.tessera composer https://tessera-kit.github.io/tessera-kit
composer require tessera/tessera-engine
```

Laravel discovers the service provider by itself.

## What a module is

A module is a folder with a service provider that extends `Tessera\Engine\ModuleServiceProvider`. The folder can sit in `app/Modules/<Name>` or come from a Composer package that lists the provider in `extra.tessera.modules`. Everything else is loaded from conventional places inside the folder:

| In the module folder | Loaded as |
|---|---|
| `Routes/web.php`, `Routes/api.php` | the web and API routes (API under `/api/v1`) |
| `Database/Migrations` | migrations |
| `Resources/lang/en.json`, `ar.json` | frontend translations |
| `Resources/js/Pages` | Inertia pages |
| `config.php` | config merged under the module key |

The provider declares its identity and what it contributes:

```php
class InvoicesServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(key: 'invoices', name: 'Invoices', requires: ['sales'], packages: ['dompdf/dompdf']);
    }

    public function menu(): array
    {
        return [new MenuItem(group: 'sales', label: 'invoices.nav', route: 'invoices.index', icon: 'FileText', permission: 'view invoices')];
    }
}
```

The hooks a provider can override: `features()` (plan features), `permissionGroups()`, `defaultRolePermissions()`, `extraPermissions()`, `menu()`, `schedule()`, `exports()`, `dashboardWidgets()`, `seeders()`, `sharedProps()` and `bindings()`. The engine collects them from all enabled modules, so the project never edits a central list.

## Commands

| Command | What it does |
|---|---|
| `module:list` | Shows the installed modules, their version, type, whether they are enabled and what they require. |
| `module:make Invoices [--requires=sales] [--group=sales]` | Scaffolds a module in `app/Modules` with a provider, controller, route, Vue page, English and Arabic texts, a migrations folder and a passing test. |
| `module:add invoices [--constraint=^1.2] [--build]` | Installs a module package: adds the Tessera Composer repository when missing, requires the package, migrates and syncs permissions. |
| `module:check [--strict]` | Checks that modules stay portable: manifests, imports of modules that are not required, translation keys, routes guarded by permissions nobody declares, duplicate migrations, unresolvable contracts. |
| `module:package <key>` | Copies a module of the project into a Composer package. |
| `module:publish catalog [--pages] [--force] [--no-composer]` | Takes a module into your project so you can edit its models, migrations, services, controllers, pages, translations and tests. See below. |

## Editing a module you installed

An installed module lives in `vendor`, so changes there are lost on the next update. Two ways to make it yours:

- **`php artisan module:publish catalog`** copies the whole module into `app/Modules/Catalog` and makes your `composer.json` *provide* the package (`"replace": {"tessera/tessera-catalog": "*"}`), so Composer stops installing the vendor copy while every package that requires it stays satisfied. The migrations keep their names, so your database is not migrated again. From then on you maintain the code, and updates of that package no longer reach your project. It works for modules that are a package of their own; the modules inside a larger package (Auth and Team, Tenant, Subscription) can be published with `--pages` only.
- **`php artisan module:publish catalog --pages`** copies only the Vue pages into `resources/js/Pages`. A page of the project replaces the module's page of the same name, so you change a screen without taking over the module, and the module keeps updating. Compare your copies with the module's pages after an update. A page you already have is kept unless you add `--force`.

`--no-composer` edits `composer.json` but leaves running `composer update <package>` to you. Projects made before this command need the new `resources/js/utils/pages.js` from the starter, which is what lets a project page replace a module page.

## Configuration

Two files are merged by the engine (publish them to change defaults):

- `modules.php`: `MODULES_ENABLED` is `*`, a comma separated list of keys, or `none`; `MODULES_LAYERS` is `web,api`, `web` or `api` (API-only projects need no Inertia); `extra` lists provider classes that live elsewhere.
- `tessera.php`: the Composer vendor, package prefix and repository URL that `module:add` uses, the translation files `module:check` treats as shared, and the shared contracts it verifies.

## Rules the engine relies on

- A module only uses another module's models, services and actions when its manifest requires it. Controllers are never shared.
- Class names are stored in the database (permissions and media use them), so a module keeps its `App\Modules\<Name>\` namespace wherever it is installed.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Tessera-kit/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
