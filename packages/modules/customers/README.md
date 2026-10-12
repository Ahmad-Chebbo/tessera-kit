# Tessera Customers

A customer list with contact details and customer groups. When Sales is installed, a customer's order history appears on their page.

A module for [Tessera](https://github.com/Ahmad-Chebbo/tessera-kit). It needs a project made from the [Tessera starter](https://github.com/Ahmad-Chebbo/tessera-starter) (the engine, the core and the interface kit).

## Install

```bash
php artisan module:add customers
bun run build
```

`module:add` requires `ahmad-chebbo/tessera-customers`, runs the migrations and gives the roles of every company the new permissions. Without it:

```bash
composer config repositories.tessera --json '{"type":"composer","url":"https://ahmad-chebbo.github.io/tessera-kit"}'
composer require ahmad-chebbo/tessera-customers
php artisan migrate
php artisan modules:sync
bun run build
```

## What it needs

- Other Tessera modules: `company`. Composer installs them for you.

## What it adds

| Plan feature | Permissions |
|---|---|
| Customers | view / create / edit / delete customers |

Menu entries: Customers, Customer groups.

- Shows a customer's order history on their page through the `CustomerActivity` contract, so the page works without Sales and gains the history when Sales is installed.


## Turn it off without removing it

Set `MODULES_ENABLED` in `.env` to the modules you want, for example `MODULES_ENABLED=catalog,sales`. Core modules are always on. A module that is left out disappears from the menu, the routes and the plan features.

## Develop

The module lives in `packages/modules/customers` of the monorepo, with its tests in `Tests/Feature`. Run `php artisan module:check` after changing it: it verifies that the module only uses what it declares.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Ahmad-Chebbo/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
