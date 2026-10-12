# Tessera Base

The base of [Tessera](https://github.com/Tessera-kit/tessera-kit): sign-in, users, roles and permissions, plus the shared contracts that let feature modules work together without depending on each other. It has no tenants, branches or plans, so it fits a project that only needs people and access control. Add [`tessera-tenant`](https://github.com/Tessera-kit/tessera-tenant) on top when you need the business, its branches and stock locations (the feature modules do), and [`tessera-saas`](https://github.com/Tessera-kit/tessera-saas) when one install serves many businesses.

It needs [`tessera-engine`](https://github.com/Tessera-kit/tessera-engine) and installs with the [fresh starter](https://github.com/Tessera-kit/tessera-fresh). To add it to an existing project:

```bash
composer config repositories.tessera composer https://tessera-kit.github.io/tessera-kit
composer require tessera/tessera-base
php artisan migrate               # also creates the permissions and default roles of the installed modules
php artisan tessera:install       # asks for the first account, which becomes the Owner
```

## The two modules

Core modules are always enabled and cannot be switched off.

| Module | What it does | Permissions it declares |
|---|---|---|
| **Auth** | Sign-in, registration, password reset, email verification, profile, and API tokens (Sanctum). | none |
| **Team** | Users, roles and permissions. `php artisan modules:sync`, which also runs after every migration, creates the permissions of every installed module and gives the template roles their defaults; `php artisan tessera:install` creates the first account. Optionally employee profiles and an activity log. | view / create / edit / delete users, manage roles, view activity log |

### Team options

Employee profiles and the activity log follow the Tenant module: on when `tessera-tenant` is installed, off without it. Decide yourself in `config/team.php` (`php artisan vendor:publish --tag=tessera-team-config`) or with `TEAM_EMPLOYEE_PROFILES` and `TEAM_ACTIVITY_LOG` set to `true` or `false`.

Without the Tenant module every user belongs to the same, unnamed organisation: the tenant and branch columns stay empty, and the roles are the template roles (Owner, Manager) plus the ones you create.

## The shared vocabulary

`Tessera\Core\Contracts` holds small interfaces that let a module use another one without depending on it: `Tenancy`, `BranchScope`, `PlanLimits`, `LocationUsage`, `CustomerActivity`, `RevenueSource`, `CartPricing`, `NetProfit` and `PurchaseLookup`. The base binds a default that does nothing for each, so a module works when the module that would fill the contract is not installed; the module that does fill it binds its own class, which wins. `tessera-saas` fills `BranchScope` (Tenant) and `PlanLimits` (Subscription).

It also holds the value objects of pricing (`CartLine`, `PricingContext`, `DiscountResult`) and the shared HTTP pieces (`HomeController`, `PeriodRequest`).

## Notes

- Packages used: Inertia, Ziggy, spatie/laravel-permission, spatie/laravel-medialibrary and Sanctum.
- The namespace stays `Tessera\Core` and `App\Modules\{Auth,Team}` because the database stores class names.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Tessera-kit/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
