# Tessera SaaS

The SaaS layer of [Tessera](https://github.com/Tessera-kit/tessera-kit): tenants with branches and locations, and subscriptions with plans and limits. It builds on [`tessera-base`](https://github.com/Tessera-kit/tessera-base) (sign-in, users and roles), which it installs with it. Feature modules (sales, inventory, expenses, …) build on it.

It replaces the old `tessera-core` package (same classes, same namespaces): `composer require tessera/tessera-saas` removes `tessera-core` for you. It installs with the [starter](https://github.com/Tessera-kit/tessera-starter). To add it to a project that started from the base:

```bash
composer config repositories.tessera composer https://tessera-kit.github.io/tessera-kit
composer require tessera/tessera-saas
php artisan migrate
php artisan modules:sync
```

## The two modules

Core modules are always enabled and cannot be switched off.

| Module | What it does | Permissions it declares |
|---|---|---|
| **Tenant** | Tenants (multi-tenant data scoping), branches, stock locations, settings, exchange rates and currencies. | manage tenants, manage branches, manage locations, manage settings, view all branches |
| **Subscription** | Plans, subscriptions, usage limits, expiry gating, and the plan-management screens for the platform admin. | none (plan features gate the routes of other modules) |

## How it plugs into the base

The base does not know about tenants or plans. This package fills the contracts the base defines (`BranchScope` with the Tenant module, `PlanLimits` with the Subscription module), adds the `tenant` and `branch` relations to users, roles and employee profiles, adds the foreign keys to the tenant and branch columns the base keeps, and shares the plan, branch and currency props with the pages. It also registers the `module` and `subscription.usable` middleware.

## Notes

- Roles are per tenant. A tenant starts with the template roles; `modules:sync` adds the permissions of newly installed modules to the matching roles of existing tenants.
- `config/currencies.php` lists the currencies a tenant can choose (`php artisan vendor:publish --tag=tessera-tenant-config`).

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Tessera-kit/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
