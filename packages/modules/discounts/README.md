# Tessera Discounts

Discount rules and promotion templates with a condition builder and a simulator, applied automatically at the till.

A module for [Tessera](https://github.com/Ahmad-Chebbo/tessera-kit). It needs a project made from the [Tessera starter](https://github.com/Ahmad-Chebbo/tessera-starter) (the engine, the core and the interface kit).

## Install

```bash
php artisan module:add discounts
bun run build
```

`module:add` requires `ahmad-chebbo/tessera-discounts`, runs the migrations and gives the roles of every company the new permissions. Without it:

```bash
composer config repositories.tessera composer https://ahmad-chebbo.github.io/tessera-kit
composer require ahmad-chebbo/tessera-discounts
php artisan migrate
php artisan modules:sync
bun run build
```

## What it needs

- Other Tessera modules: `catalog`, `company`, `customers`, `sales`, `subscription`, `team`. Composer installs them for you.

## What it adds

| Plan feature | Permissions |
|---|---|
| Discounts & promotions | manage discounts |

Menu entries: Discounts.

- Fills the `CartPricing` contract: Sales asks it for the discounts of a cart and works without it when it is not installed.

## Notes

- The discount columns on orders belong to Sales, so removing this module never breaks existing orders.

## Turn it off without removing it

Set `MODULES_ENABLED` in `.env` to the modules you want, for example `MODULES_ENABLED=catalog,sales`. Core modules are always on. A module that is left out disappears from the menu, the routes and the plan features.

## Develop

The module lives in `packages/modules/discounts` of the monorepo, with its tests in `Tests/Feature`. Run `php artisan module:check` after changing it: it verifies that the module only uses what it declares.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Ahmad-Chebbo/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
