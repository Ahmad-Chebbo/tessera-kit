# Tessera Organization

Departments, job positions and who reports to whom, with an organization chart.

A module for [Tessera](https://github.com/Tessera-kit/tessera-kit). It needs a project made from one of the Tessera starters, the [SaaS starter](https://github.com/Tessera-kit/tessera-starter) or the [fresh starter](https://github.com/Tessera-kit/tessera-fresh): the engine, the base and the interface kit.

## Install

```bash
php artisan module:add organization
bun run build
```

`module:add` requires `tessera/tessera-organization`, runs the migrations and gives the roles of every tenant the new permissions. Without it:

```bash
composer config repositories.tessera composer https://tessera-kit.github.io/tessera-kit
composer require tessera/tessera-organization
php artisan migrate
php artisan modules:sync
bun run build
```

## What it needs

- Other Tessera modules: `tenant`, `team`. Composer installs them for you.

## What it adds

| Plan feature | Permissions |
|---|---|
| Organization chart | view organization, manage organization |

Menu entries: Organization, with the chart, the employees, the departments and the positions on one page.

## Notes

- Departments can sit inside other departments, and each can have a head. A position can belong to a department.
- Each employee gets a department, a position and a manager. A manager cannot be their own report, directly or through the chain, and everything stays inside the tenant.
- Other modules ask who someone reports to through the `ReportingLines` contract of the base package. Without this module nobody has a manager. Leave requests, for one, go to the manager when it is installed.

## Turn it off without removing it

Set `MODULES_ENABLED` in `.env` to the modules you want, for example `MODULES_ENABLED=organization`. The core modules (Auth, Team and Tenant) are always on. A module that is left out disappears from the menu, the routes and the plan features.

## Develop

The module lives in `packages/modules/organization` of the monorepo, with its tests in `Tests/Feature`. Run `php artisan module:check` after changing it: it verifies that the module only uses what it declares.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Tessera-kit/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
