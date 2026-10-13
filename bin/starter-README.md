# Tessera Starter

A Laravel 13 application ready for [Tessera](https://github.com/Tessera-kit/tessera-kit) modules. It contains the module engine, the base (sign-in, users and roles), the tenant package (the business, branches, stock locations, settings) and the SaaS package (many tenants, plans and subscriptions), the interface kit and the application shell (sidebar layout, language and theme toggles). Business features arrive as modules.

## Create a project

```bash
composer create-project tessera/tessera-starter shop --repository-url=https://tessera-kit.github.io/tessera-kit
cd shop
bun install
```

`create-project` copies `.env.example` to `.env`, generates the key and runs the migrations when the database is reachable. Set `DB_*` in `.env` and run `php artisan migrate` if it was not. Then seed the plans, create the platform admin and build the frontend:

```bash
php artisan db:seed             # the plans and a demo tenant
php artisan tessera:install     # asks for the platform admin's name, email and password
bun run build                   # or: bun run dev
```

For a local demo, the seeder also creates `admin@app.com` / `password`; never use it in production.

Requirements: PHP 8.4, Composer, [bun](https://bun.sh), a database supported by Laravel.

## Add modules

```bash
php artisan module:add sales       # the till and the orders; also installs catalog, customers and inventory
php artisan module:add expenses
php artisan module:add reports
bun run build
```

| Module | What it adds |
|---|---|
| [`sales`](https://github.com/Tessera-kit/tessera-sales) | the till and the orders together |
| [`orders`](https://github.com/Tessera-kit/tessera-orders) | orders, voiding, credit sales, receipts |
| [`pos`](https://github.com/Tessera-kit/tessera-pos) | the till, held orders, offline sales, cash-drawer shifts |
| [`catalog`](https://github.com/Tessera-kit/tessera-catalog) | products and categories |
| [`inventory`](https://github.com/Tessera-kit/tessera-inventory) | stock, transfers, suppliers, purchases |
| [`customers`](https://github.com/Tessera-kit/tessera-customers) | customers and groups |
| [`discounts`](https://github.com/Tessera-kit/tessera-discounts) | discount rules and promotions |
| [`expenses`](https://github.com/Tessera-kit/tessera-expenses) | expenses, recurring expenses |
| [`reports`](https://github.com/Tessera-kit/tessera-reports) | dashboard, sales, items and profit reports |
| [`exports`](https://github.com/Tessera-kit/tessera-exports) | Excel and PDF export of reports |
| [`organization`](https://github.com/Tessera-kit/tessera-organization) | departments, positions, managers and an organization chart |
| [`shifts`](https://github.com/Tessera-kit/tessera-shifts) | shift templates and rosters |
| [`attendance`](https://github.com/Tessera-kit/tessera-attendance) | check-in and check-out, absences |

Each command requires the package, migrates, and gives the roles of every tenant the new permissions. `php artisan module:list` shows what is installed.

## Change a module

```bash
php artisan module:publish catalog          # take the whole module into app/Modules to edit it
php artisan module:publish catalog --pages  # copy only its Vue pages; they replace the module's own
```

The first stops that module updating through Composer. The second keeps it updating while your pages take over the screens you copied.

## Write your own module

```bash
php artisan module:make Invoices --requires=orders
php artisan module:check
```

The module appears in `app/Modules/Invoices` with a provider, route, page, translations and a passing test. When it is ready for other projects, `php artisan module:package invoices` turns it into a Composer package.

## Layout

- `app/Modules` — your own modules.
- `resources/js` — the application shell: layouts, `app.js`, the page resolver and the translation merge. Modules import the layout as `@/Layouts/SidebarLayout.vue`, so keep it.
- `vite.modules.js` — lets `@modules/<Name>/…` imports reach modules installed in `vendor`, also while `bun run dev` is running.
- `config/tessera.php` / `config/modules.php` — optional; the engine provides the defaults.

## Useful settings

- `MODULES_ENABLED` — `*` (default), a comma separated list of module keys, or `none`.
- `MODULES_LAYERS` — `web,api` (default), `web`, or `api` for a headless project.

## Tests

```bash
php artisan test
bun run test
```

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Tessera-kit/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
