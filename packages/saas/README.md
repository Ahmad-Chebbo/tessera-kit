# Tessera SaaS

The SaaS layer of [Tessera](https://github.com/Tessera-kit/tessera-kit): plans, subscriptions and limits, and the screens a platform admin uses to create and manage tenants. Add it when one install serves several businesses. A project with a single business does not need it.

It builds on [`tessera-tenant`](https://github.com/Tessera-kit/tessera-tenant) (the business, its branches and settings) and [`tessera-base`](https://github.com/Tessera-kit/tessera-base) (sign-in, users and roles), which it installs with it. It comes ready in the [SaaS starter](https://github.com/Tessera-kit/tessera-starter). To add it to a project that already has the tenant package:

```bash
composer config repositories.tessera composer https://tessera-kit.github.io/tessera-kit
composer require tessera/tessera-saas
php artisan migrate
php artisan modules:sync
```

## The Subscription module

| | |
|---|---|
| **Tenants** | The platform admin creates a tenant with its owner account, a main branch and a plan, edits it and switches it off. |
| **Plans** | The modules a plan includes, its limits (such as the number of users or branches) and its price. Pages, menus and permissions of a module a plan leaves out disappear for that tenant. |
| **Subscriptions** | Each tenant has a subscription to a plan, with a trial or an end date. An expired subscription sends people to a page that says so. |

Permissions it declares: manage tenants (held by the platform *Admin* role).

It is an ordinary feature module, not a core one: `MODULES_ENABLED` can leave it out, and the project then behaves as a single business.

## How it plugs in

The modules that check plan limits (the user limit in Team, the module gate in the `module` middleware, the shift and discount checks in Sales and Discounts) ask the `PlanLimits` contract, which allows everything by default. This package binds the real one. It also adds the subscription relations to the tenant, registers the `subscription.usable` middleware, and shares the plan with the pages. With this package, roles belong to each tenant instead of being shared.

## Upgrading from version 2

The Tenant module (the business, branches, locations, settings) moved to `tessera-tenant`, and *company* became *tenant* everywhere. `composer update` installs the new package for you. The tenant administration screens that were in the Company module now live here.

This package replaces the old `tessera-core`: `composer require tessera/tessera-saas` removes it.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Tessera-kit/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
