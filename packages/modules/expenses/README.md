# Tessera Expenses

Expense categories, expenses with receipts, recurring expenses, and the expense side of net profit.

A module for [Tessera](https://github.com/Ahmad-Chebbo/tessera-kit). It needs a project made from the [Tessera starter](https://github.com/Ahmad-Chebbo/tessera-starter) (the engine, the core and the interface kit).

## Install

```bash
php artisan module:add expenses
bun run build
```

`module:add` requires `ahmad-chebbo/tessera-expenses`, runs the migrations and gives the roles of every company the new permissions. Without it:

```bash
composer config repositories.tessera --json '{"type":"composer","url":"https://ahmad-chebbo.github.io/tessera-kit"}'
composer require ahmad-chebbo/tessera-expenses
php artisan migrate
php artisan modules:sync
bun run build
```

## What it needs

- Other Tessera modules: none besides the core.
- Composer packages: `spatie/laravel-medialibrary`.

## What it adds

| Plan feature | Permissions |
|---|---|
| Expenses | view / create / edit / delete expenses |

Menu entries: Expenses.

- Exports two reports through the Exports module: expenses and expenses breakdown.
- Has API routes (`/api/v1`).
- Fills the `NetProfit` contract; Reports shows net profit when this module is installed.
- Can link an expense to a received purchase when Inventory is installed (through the `PurchaseLookup` contract).

## Notes

- Receipts are stored with spatie/laravel-medialibrary.

## Turn it off without removing it

Set `MODULES_ENABLED` in `.env` to the modules you want, for example `MODULES_ENABLED=catalog,sales`. Core modules are always on. A module that is left out disappears from the menu, the routes and the plan features.

## Develop

The module lives in `packages/modules/expenses` of the monorepo, with its tests in `Tests/Feature`. Run `php artisan module:check` after changing it: it verifies that the module only uses what it declares.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Ahmad-Chebbo/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
