# Tessera Inventory

Stock per branch and location, stock movements, adjustments and transfers, suppliers and purchase orders with receiving.

A module for [Tessera](https://github.com/Ahmad-Chebbo/tessera-kit). It needs a project made from the [Tessera starter](https://github.com/Ahmad-Chebbo/tessera-starter) (the engine, the core and the interface kit).

## Install

```bash
php artisan module:add inventory
bun run build
```

`module:add` requires `ahmad-chebbo/tessera-inventory`, runs the migrations and gives the roles of every company the new permissions. Without it:

```bash
composer config repositories.tessera composer https://ahmad-chebbo.github.io/tessera-kit
composer require ahmad-chebbo/tessera-inventory
php artisan migrate
php artisan modules:sync
bun run build
```

## What it needs

- Other Tessera modules: `catalog`, `company`, `team`. Composer installs them for you.

## What it adds

| Plan feature | Permissions |
|---|---|
| Inventory & purchasing | view inventory, adjust inventory, transfer stock, view / create / receive purchases, view / create / edit / delete suppliers |

Menu entries: Inventory, Suppliers, Purchases, Stock movements.

- Adds a low-stock widget to the dashboard of Reports.
- Exports two reports: stock levels and stock movements.
- Fills the `LocationUsage` and `PurchaseLookup` contracts.


## Turn it off without removing it

Set `MODULES_ENABLED` in `.env` to the modules you want, for example `MODULES_ENABLED=catalog,sales`. Core modules are always on. A module that is left out disappears from the menu, the routes and the plan features.

## Develop

The module lives in `packages/modules/inventory` of the monorepo, with its tests in `Tests/Feature`. Run `php artisan module:check` after changing it: it verifies that the module only uses what it declares.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Ahmad-Chebbo/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
