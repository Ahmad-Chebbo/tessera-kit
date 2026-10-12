# Tessera Catalog

Products and categories with units, prices, barcodes and pictures. Every module that sells or stocks something builds on it.

A module for [Tessera](https://github.com/Tessera-kit/tessera-kit). It needs a project made from the [Tessera starter](https://github.com/Tessera-kit/tessera-starter) (the engine, the core and the interface kit).

## Install

```bash
php artisan module:add catalog
bun run build
```

`module:add` requires `tessera/tessera-catalog`, runs the migrations and gives the roles of every tenant the new permissions. Without it:

```bash
composer config repositories.tessera composer https://tessera-kit.github.io/tessera-kit
composer require tessera/tessera-catalog
php artisan migrate
php artisan modules:sync
bun run build
```

## What it needs

- Other Tessera modules: `tenant`. Composer installs them for you.
- Composer packages: `spatie/laravel-medialibrary`.

## What it adds

| Plan feature | Permissions |
|---|---|
| Products & categories | view / create / edit / delete products, view / create / edit / delete categories |

Menu entries: Products, Categories.

- Seeds the standard units of measure (`UnitSeeder`).

## Notes

- Product pictures are stored with spatie/laravel-medialibrary, so run its migration (the starter already does).

## Turn it off without removing it

Set `MODULES_ENABLED` in `.env` to the modules you want, for example `MODULES_ENABLED=catalog,sales`. Core modules are always on. A module that is left out disappears from the menu, the routes and the plan features.

## Develop

The module lives in `packages/modules/catalog` of the monorepo, with its tests in `Tests/Feature`. Run `php artisan module:check` after changing it: it verifies that the module only uses what it declares.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Tessera-kit/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
