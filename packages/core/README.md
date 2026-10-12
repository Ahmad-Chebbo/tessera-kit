# Tessera Core

The SaaS core of [Tessera](https://github.com/Ahmad-Chebbo/tessera-kit): sign-in, companies with branches and locations, subscriptions with plans and limits, and users with roles and permissions. Feature modules (sales, inventory, expenses, …) build on it.

It needs [`tessera-engine`](https://github.com/Ahmad-Chebbo/tessera-engine) and installs with the [starter](https://github.com/Ahmad-Chebbo/tessera-starter). To add it to an existing project:

```bash
composer config repositories.tessera composer https://ahmad-chebbo.github.io/tessera-kit
composer require ahmad-chebbo/tessera-core
php artisan migrate
php artisan modules:sync
```

## The four core modules

Core modules are always enabled and cannot be switched off.

| Module | What it does | Permissions it declares |
|---|---|---|
| **Auth** | Sign-in, registration, password reset, email verification, profile, and API tokens (Sanctum). | none |
| **Company** | Companies (multi-tenant data scoping), branches, stock locations, settings and exchange rates. | manage companies, manage branches, manage locations, manage settings, view all branches |
| **Subscription** | Plans, subscriptions, usage limits, expiry gating, and the plan-management screens for the platform admin. | none (plan features gate the routes of other modules) |
| **Team** | Users, custom roles, the activity log, and `php artisan modules:sync`, which creates the permissions of every installed module and gives the template roles their defaults. | view / create / edit / delete users, manage roles, view activity log |

## The shared vocabulary

`Tessera\Core\Contracts` holds small interfaces that let a feature module use another one without depending on it: `LocationUsage`, `CustomerActivity`, `RevenueSource`, `CartPricing`, `NetProfit` and `PurchaseLookup`. The core binds a default that does nothing for each, so a module works when the module that would fill the contract is not installed; the module that does fill it binds its own class, which wins.

It also holds the value objects of pricing (`CartLine`, `PricingContext`, `DiscountResult`) and the shared HTTP pieces (`HomeController`, `PeriodRequest`).

## Notes

- Packages used: Inertia, Ziggy, spatie/laravel-permission, spatie/laravel-medialibrary and Sanctum.
- Roles are per company. A company starts with the template roles; `modules:sync` adds the permissions of newly installed modules to the matching roles of existing companies.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Ahmad-Chebbo/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
