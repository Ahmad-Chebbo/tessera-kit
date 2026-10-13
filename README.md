# Tessera

A modular kit for Laravel business applications. Install a small base, then add the features you need as modules, the way you add plugins to WordPress, but as normal Composer packages.

```bash
composer create-project tessera/tessera-starter shop --repository-url=https://tessera-kit.github.io/tessera-kit
cd shop && bun install
php artisan module:add sales
bun run build
```

Start from the [starter](https://github.com/Tessera-kit/tessera-starter) to build a multi-tenant app, or from [fresh](https://github.com/Tessera-kit/tessera-fresh) for a plain one with users and roles. This repository is where Tessera itself is developed.

## The pieces

| Package | Repository | Installed with | What it is |
|---|---|---|---|
| Engine | [tessera-engine](https://github.com/Tessera-kit/tessera-engine) | Composer | Finds, orders and boots modules; `module:*` commands. Works in any Laravel app. |
| Base | [tessera-base](https://github.com/Tessera-kit/tessera-base) | Composer | Auth and Team (users, roles, permissions), plus the shared contracts. Works alone. |
| Tenant | [tessera-tenant](https://github.com/Tessera-kit/tessera-tenant) | Composer | The business: its branches, stock locations, currencies and settings. One business or many. Builds on the base. |
| SaaS | [tessera-saas](https://github.com/Tessera-kit/tessera-saas) | Composer | Plans, subscriptions and the screens to manage many tenants. Builds on the tenant package. |
| UI | [tessera-ui](https://github.com/Tessera-kit/tessera-ui) | bun / npm | Vue 3 components, composables and the theme. Standalone. |
| Modules | `tessera-<key>` | Composer | Attendance, Catalog, Customers, Discounts, Expenses, Exports, Inventory, Orders, Organization, Pos, Reports, Sales (the two together), Shifts. |
| Starter | [tessera-starter](https://github.com/Tessera-kit/tessera-starter) | `composer create-project` | A Laravel SaaS app with the engine, base, tenant, saas, UI and application shell. |
| Fresh | [tessera-fresh](https://github.com/Tessera-kit/tessera-fresh) | `composer create-project` | The same without tenants and plans: sign-in, users, roles and permissions. Modules you add make it a single business. |

Every package has a README. They are read-only copies of folders here.

## How the monorepo is laid out

```
packages/engine           Tessera\Engine         the module engine
packages/base             Tessera\Core           contracts + modules/{Auth,Team}
packages/tenant           App\Modules\Tenant     modules/Tenant
packages/saas             App\Modules\Subscription  modules/Subscription
packages/ui               @tessera/tessera-ui   the interface kit (npm)
packages/modules/<key>    App\Modules\<Name>    one folder per feature module
app, resources, routes    the host application (it becomes the starter)
bin/build-starter.php     builds the starter project from the host application
tests/                    core and product tests
```

The host application installs everything from `packages/` through Composer path repositories, exactly as a customer project installs it from the published repository, so what you run here is what users get.

## Work on it

```bash
composer install
cp .env.example .env && php artisan key:generate
bun install
php artisan migrate --seed
bun run dev            # or: bun run build
php artisan test       # all packages' tests
bun run test           # frontend tests
php artisan module:check --strict
```

Rules that keep modules reusable (checked by `module:check`): a module imports another module only when its manifest requires it; contracts replace direct dependencies on optional modules; class names stay under `App\Modules\<Name>\` because the database stores them.

## Changing a module

`php artisan module:publish <key>` takes an installed module into your project (`app/Modules/<Name>`) so you can edit its models, migrations, services, pages and translations, and stops it updating through Composer. `php artisan module:publish <key> --pages` copies only its Vue pages into `resources/js/Pages`, where they replace the module's pages of the same name and the module keeps updating. See the [engine README](packages/engine/README.md) for the details. A project made before version 4 needs the new `resources/js/utils/pages.js` for page overrides.

## Upgrading from 3.x

Version 4 split the Sales module in two: [`tessera-orders`](https://github.com/Tessera-kit/tessera-orders) keeps the order records and screens, and [`tessera-pos`](https://github.com/Tessera-kit/tessera-pos) is the till. `tessera-sales` stays as a bundle that installs both, so `php artisan module:add sales` and `MODULES_ENABLED=...sales` keep working once the two modules are listed too. `composer update` installs the new packages and `php artisan migrate` rewrites the class names stored in the database. In your own code:

- `App\Modules\Sales\Models\Order`, `OrderItem` and `Payment` (and the order services, actions, enum and export) are now under `App\Modules\Orders\`; `PosShift`, `HeldOrder`, `CheckoutAction` and the till's controllers are under `App\Modules\Pos\`.
- A module of yours that has `requires: ['sales']` and reads orders should require `orders`.
- The `pos` plan feature no longer includes the orders: they are the `orders` feature. The migration gives every plan that had `pos` the `orders` feature too.
- Checkout from your own code: `PlaceOrderAction` in Orders places an order; the Pos module's `CheckoutAction` wraps it with the shift check.

## Upgrading from 2.x

Version 3 renamed *company* to *tenant* everywhere and split the SaaS package in two: [`tessera-tenant`](https://github.com/Tessera-kit/tessera-tenant) holds the business (branches, stock locations, currencies, settings) and works for one business or many, and `tessera-saas` keeps plans, subscriptions and the screens to manage tenants. The modules no longer need the SaaS package, only the tenant one. Users, roles and the other base tables no longer have tenant columns of their own; the tenant package adds them.

`composer update` installs the new package. Run `php artisan migrate`: the migrations rename the `companies` table to `tenants`, every `company_id` column to `tenant_id`, the *manage companies* permission to *manage tenants*, and the class names stored in the activity log and in media. Then, in your own code:

- `App\Modules\Company\…` becomes `App\Modules\Tenant\…`, and `$user->company` becomes `$user->tenant`.
- `company_id` becomes `tenant_id` in your queries and in your own modules' migrations, and the `company` module key in `requires: ['company']` becomes `tenant`.
- Routes and pages named `companies` are now `tenants` (`/admin/tenants`), and so are the translation keys.

## Upgrading from 1.x

Version 2.0 moved the project to the GitHub organization [Tessera-kit](https://github.com/Tessera-kit) and renamed the packages: the Composer vendor is now `tessera` (`tessera/tessera-engine`, `tessera/tessera-sales`, …), the interface kit is `@tessera/tessera-ui`, and the Composer repository is `https://tessera-kit.github.io/tessera-kit`. Class names, namespaces and the database are unchanged. In an existing project:

```bash
composer config repositories.tessera composer https://tessera-kit.github.io/tessera-kit
composer remove ahmad-chebbo/tessera-engine ahmad-chebbo/tessera-saas   # and every other ahmad-chebbo/* package it requires
composer require tessera/tessera-engine tessera/tessera-saas            # and the modules you use
# in package.json and resources/js, replace @ahmad-chebbo/tessera-ui with @tessera/tessera-ui, pin it to github:Tessera-kit/tessera-ui#v2.0.0
bun install && bun run build
```

Each new package declares that it replaces the old one, so Composer drops the old copies as it installs the new ones.

## Release

Tag the monorepo (`git tag v4.0.0 && git push --tags`). The **Split** workflow copies each package to its repository with the same tag and publishes the starter; the **Repository** workflow then rebuilds the Composer repository at `https://tessera-kit.github.io/tessera-kit` that lists every package. The **Tests** workflow runs on every push. The workflows need a repository secret `SPLIT_TOKEN` (a token that may push to the package repositories).

## Add a module

```bash
php artisan module:make Invoices --requires=orders    # in a project made from a starter
```

To add it to the monorepo as a package, create `packages/modules/invoices` with a `composer.json` like its neighbours, require it in the root `composer.json`, add its repository to the matrix of `.github/workflows/split.yml` and to `.github/satis.json`.

## License

All rights reserved until a license is added.
