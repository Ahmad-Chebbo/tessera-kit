# Tessera Tenant

The business behind a [Tessera](https://github.com/Tessera-kit/tessera-kit) app: its name and settings, its branches, its stock locations, its currencies and exchange rates. The feature modules (catalog, inventory, sales, expenses, …) keep their data per tenant and need this package.

It works for **one business or many**. A project with one business gets one tenant, made on first use with a main branch and its stock location, and every user belongs to it. Many tenants, plans and the screens to manage them come from [`tessera-saas`](https://github.com/Tessera-kit/tessera-saas), which is only needed when one install serves several businesses.

It needs [`tessera-base`](https://github.com/Tessera-kit/tessera-base) (sign-in, users and roles), which it installs with it. Modules that need it install it for you with `php artisan module:add`. To add it by hand:

```bash
composer config repositories.tessera composer https://tessera-kit.github.io/tessera-kit
composer require tessera/tessera-tenant
php artisan migrate
php artisan modules:sync
```

## What the Tenant module does

| | |
|---|---|
| **Tenant** | The business: name, contact details, logo, currency, language, time zone, tax rate and the text of receipts. |
| **Branches and locations** | The branches of the business and the places stock is kept (a branch's shelves, a warehouse). People can be limited to their own branch. |
| **Exchange rates and currencies** | The currencies a tenant can use and the rates between them. `config/currencies.php` lists them (`php artisan vendor:publish --tag=tessera-tenant-config`). |
| **Settings** | A page for the business details, for people who hold *manage settings*. |

Permissions it declares: manage settings, manage branches, manage locations, view all branches.

## One business or many

- **One business** (no `tessera-saas`): `Tenant::default()` is the only tenant. New users are put in it and in its main branch, and the roles (Owner, Manager, …) are shared by everyone.
- **Many tenants** (with `tessera-saas`): every tenant has its own users, branches, stock and roles, and a platform admin creates them.

Moving from one to the other is `composer require tessera/tessera-saas`, `php artisan migrate` and `php artisan saas:install --admin-email=you@example.com`: the business becomes the first of many tenants, with its own roles and a plan, and the platform admin is created.

## How it plugs into the base

The base does not know about tenants. This package fills the `Tenancy` and `BranchScope` contracts the base defines, adds the tenant and branch columns to users, roles, employee profiles and the activity log (and the foreign keys where the database supports them), adds the `tenant` and `branch` relations to them, and shares the currency and branch props with the pages.

## Upgrading from version 2

Version 3 moved this code out of `tessera-saas`, renamed *company* to *tenant* everywhere, and renamed the database to match. The migrations bring an existing database along: the `companies` table becomes `tenants`, every `company_id` column becomes `tenant_id`, and the *manage companies* permission becomes *manage tenants*. Class names changed from `App\Modules\Company\…` to `App\Modules\Tenant\…`; the migrations rewrite the ones stored in the activity log and in media.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Tessera-kit/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
