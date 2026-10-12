# Tessera Core

The SaaS layer of [Tessera](https://github.com/Ahmad-Chebbo/tessera-kit): companies with branches and locations, and subscriptions with plans and limits. It builds on [`tessera-base`](https://github.com/Ahmad-Chebbo/tessera-base) (sign-in, users and roles), which it installs with it. Feature modules (sales, inventory, expenses, …) build on it.

It installs with the [starter](https://github.com/Ahmad-Chebbo/tessera-starter). To add it to a project that started from the base:

```bash
composer config repositories.tessera composer https://ahmad-chebbo.github.io/tessera-kit
composer require ahmad-chebbo/tessera-core
php artisan migrate
php artisan modules:sync
```

## The two core modules

Core modules are always enabled and cannot be switched off.

| Module | What it does | Permissions it declares |
|---|---|---|
| **Company** | Companies (multi-tenant data scoping), branches, stock locations, settings, exchange rates and currencies. | manage companies, manage branches, manage locations, manage settings, view all branches |
| **Subscription** | Plans, subscriptions, usage limits, expiry gating, and the plan-management screens for the platform admin. | none (plan features gate the routes of other modules) |

## How it plugs into the base

The base does not know about companies or plans. This package fills the contracts the base defines (`BranchScope` with the Company module, `PlanLimits` with the Subscription module), adds the `company` and `branch` relations to users, roles and employee profiles, adds the foreign keys to the company and branch columns the base keeps, and shares the plan, branch and currency props with the pages. It also registers the `module` and `subscription.usable` middleware.

## Notes

- Roles are per company. A company starts with the template roles; `modules:sync` adds the permissions of newly installed modules to the matching roles of existing companies.
- `config/currencies.php` lists the currencies a company can choose (`php artisan vendor:publish --tag=tessera-company-config`).

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Ahmad-Chebbo/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
