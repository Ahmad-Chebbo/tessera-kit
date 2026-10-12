# Tessera Base

The base of [Tessera](https://github.com/Ahmad-Chebbo/tessera-kit): sign-in, users, roles and permissions, plus the shared contracts that let feature modules work together without depending on each other. It has no companies, branches or plans, so it fits a project that only needs people and access control. Add [`tessera-saas`](https://github.com/Ahmad-Chebbo/tessera-saas) on top when you need those.

It needs [`tessera-engine`](https://github.com/Ahmad-Chebbo/tessera-engine) and installs with the [fresh starter](https://github.com/Ahmad-Chebbo/tessera-fresh). To add it to an existing project:

```bash
composer config repositories.tessera composer https://ahmad-chebbo.github.io/tessera-kit
composer require ahmad-chebbo/tessera-base
php artisan migrate
php artisan modules:sync
```

## The two modules

Core modules are always enabled and cannot be switched off.

| Module | What it does | Permissions it declares |
|---|---|---|
| **Auth** | Sign-in, registration, password reset, email verification, profile, and API tokens (Sanctum). | none |
| **Team** | Users, roles, permissions, and `php artisan modules:sync`, which creates the permissions of every installed module and gives the template roles their defaults. Optionally employee profiles and an activity log. | view / create / edit / delete users, manage roles, view activity log |

### Team options

Employee profiles and the activity log follow the Company module: on when `tessera-saas` is installed, off without it. Decide yourself in `config/team.php` (`php artisan vendor:publish --tag=tessera-team-config`) or with `TEAM_EMPLOYEE_PROFILES` and `TEAM_ACTIVITY_LOG` set to `true` or `false`.

Without the Company module every user belongs to the same, unnamed organisation: the company and branch columns stay empty, and the roles are the template roles (Owner, Manager) plus the ones you create.

## The shared vocabulary

`Tessera\Core\Contracts` holds small interfaces that let a module use another one without depending on it: `BranchScope`, `PlanLimits`, `LocationUsage`, `CustomerActivity`, `RevenueSource`, `CartPricing`, `NetProfit` and `PurchaseLookup`. The base binds a default that does nothing for each, so a module works when the module that would fill the contract is not installed; the module that does fill it binds its own class, which wins. `tessera-saas` fills `BranchScope` (Company) and `PlanLimits` (Subscription).

It also holds the value objects of pricing (`CartLine`, `PricingContext`, `DiscountResult`) and the shared HTTP pieces (`HomeController`, `PeriodRequest`).

## Notes

- Packages used: Inertia, Ziggy, spatie/laravel-permission, spatie/laravel-medialibrary and Sanctum.
- The namespace stays `Tessera\Core` and `App\Modules\{Auth,Team}` because the database stores class names.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Ahmad-Chebbo/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
