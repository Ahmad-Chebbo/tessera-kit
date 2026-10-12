# Tessera Sales

The point of sale and orders: cart, checkout, receipts, offline queue, voiding, and cash-drawer shifts with closing reports.

A module for [Tessera](https://github.com/Ahmad-Chebbo/tessera-kit). It needs a project made from the [Tessera starter](https://github.com/Ahmad-Chebbo/tessera-starter) (the engine, the core and the interface kit).

## Install

```bash
php artisan module:add sales
bun run build
```

`module:add` requires `ahmad-chebbo/tessera-sales`, runs the migrations and gives the roles of every company the new permissions. Without it:

```bash
composer config repositories.tessera composer https://ahmad-chebbo.github.io/tessera-kit
composer require ahmad-chebbo/tessera-sales
php artisan migrate
php artisan modules:sync
bun run build
```

## What it needs

- Other Tessera modules: `catalog`, `company`, `customers`, `inventory`, `subscription`, `team`. Composer installs them for you.

## What it adds

| Plan feature | Permissions |
|---|---|
| Point of sale & orders | access pos, apply discounts, void orders, view orders, view all orders, export orders |
| Shifts & cash drawer | view shift reports, open pos shift |

Menu entries: POS, POS shifts, Orders.

- Exports orders, POS shifts and the shift closing report.
- Works without Discounts (no automatic discounts) and without Reports.
- Fills the `CustomerActivity` and `RevenueSource` contracts: customers show their orders and net profit can use sales revenue.

## Notes

- The POS keeps a queue in the browser (localForage) so a till can keep selling while offline and syncs later.

## Turn it off without removing it

Set `MODULES_ENABLED` in `.env` to the modules you want, for example `MODULES_ENABLED=catalog,sales`. Core modules are always on. A module that is left out disappears from the menu, the routes and the plan features.

## Develop

The module lives in `packages/modules/sales` of the monorepo, with its tests in `Tests/Feature`. Run `php artisan module:check` after changing it: it verifies that the module only uses what it declares.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Ahmad-Chebbo/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
