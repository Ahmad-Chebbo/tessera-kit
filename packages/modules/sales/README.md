# Tessera Sales

The point of sale and the orders together, for a project that wants both. It adds nothing of its own except a demo store to try them with:

- [`tessera-orders`](https://github.com/Tessera-kit/tessera-orders): orders, lines, payments, voiding, credit sales, receipts.
- [`tessera-pos`](https://github.com/Tessera-kit/tessera-pos): the till, held orders, offline sales and cash-drawer shifts.

Install Orders or Pos alone when only one is needed. A storefront, for example, needs the orders and not the till.

A module for [Tessera](https://github.com/Tessera-kit/tessera-kit).

## Install

```bash
php artisan module:add sales
bun run build
```

This installs `tessera/tessera-sales`, which requires the two modules, and their own requirements (`catalog`, `customers`, `inventory`). `php artisan db:seed` then also seeds a demo store with products and stock.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Tessera-kit/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
