# Tessera Point of sale

The till: a screen for selling at the counter with a cart, barcodes and receipts, held orders, sales queued in the browser while the connection is down, and cash-drawer shifts with closing reports. A sale is placed as an order by the [Orders](https://github.com/Tessera-kit/tessera-orders) module.

A module for [Tessera](https://github.com/Tessera-kit/tessera-kit). It needs a project made from one of the Tessera starters.

## Install

```bash
php artisan module:add pos
bun run build
```

`module:add` requires `tessera/tessera-pos` and the Orders module under it, runs the migrations and gives the roles the new permissions. Without it:

```bash
composer config repositories.tessera composer https://tessera-kit.github.io/tessera-kit
composer require tessera/tessera-pos
php artisan migrate
php artisan modules:sync
bun run build
```

## What it needs

- Other Tessera modules: `orders`, `catalog`, `tenant`, `customers`, `inventory`, `team`. Composer installs them for you.

## What it adds

| Plan feature | Permissions |
|---|---|
| Point of sale | access pos, apply discounts |
| Shifts & cash drawer | view shift reports, open pos shift |

Menu entries: POS and Cash drawer. Exports the shift list and the shift closing report.

- Works without Discounts (no automatic discounts) and without Reports.
- A shift can be required before selling: the tenant setting *require a shift* and the *shifts* plan feature decide.

## Notes

- The till keeps a queue in the browser (localForage) so it can keep selling while offline and syncs later. A sale sent twice is placed once.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Tessera-kit/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
