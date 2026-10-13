# Tessera Leave

Leave types with a yearly allowance, requests that a manager approves, public holidays, and a list of who is away.

A module for [Tessera](https://github.com/Tessera-kit/tessera-kit). It needs a project made from one of the Tessera starters, the [SaaS starter](https://github.com/Tessera-kit/tessera-starter) or the [fresh starter](https://github.com/Tessera-kit/tessera-fresh): the engine, the base and the interface kit.

## Install

```bash
php artisan module:add leave
bun run build
```

`module:add` requires `tessera/tessera-leave`, runs the migrations and gives the roles of every tenant the new permissions. Without it:

```bash
composer config repositories.tessera composer https://tessera-kit.github.io/tessera-kit
composer require tessera/tessera-leave
php artisan migrate
php artisan modules:sync
bun run build
```

## What it needs

- Other Tessera modules: `tenant`, `team`. Composer installs them for you.

## What it adds

| Plan feature | Permissions |
|---|---|
| Leave | request leave, approve leave, manage leave |

Menu entries: Leave, with my leave, approvals, who is away, leave types and holidays on one page. Employees see the first and third, approvers the second, and people who manage leave the last two.

## How it works

- A **leave type** has a yearly allowance in days, or none for a type with no limit such as unpaid leave, and says whether it is paid.
- A request counts the **working days** between its dates: weekends and holidays are left out. A request counts against the year it starts in, and a pending request holds its days until it is decided.
- A request may not overlap another one, may not start in the past (unless the person manages leave), and may not take more than what is left of the allowance. The allowance is checked again when it is approved.
- Employees cancel their own pending requests, and approved ones that have not started.
- **Who approves.** Anyone with the approve permission may decide the requests of others. When the [Organization](https://github.com/Tessera-kit/tessera-organization) module is installed, a request goes to the employee's manager, and people who manage leave can still decide any request, so an employee without a manager is not stuck. Nobody decides their own request.
- **Attendance.** When Attendance is installed, a missed shift on a day of approved leave shows as excused, with the leave type as the reason, instead of an absence.

## Settings

The weekend defaults to Saturday and Sunday. For a Friday and Saturday weekend set this in `.env`, or publish the config with `php artisan vendor:publish --tag=tessera-leave-config`:

```
LEAVE_WEEKEND=friday,saturday
```

## Turn it off without removing it

Set `MODULES_ENABLED` in `.env` to the modules you want, for example `MODULES_ENABLED=leave`. The core modules (Auth, Team and Tenant) are always on. A module that is left out disappears from the menu, the routes and the plan features.

## Develop

The module lives in `packages/modules/leave` of the monorepo, with its tests in `Tests/Feature`. Run `php artisan module:check` after changing it: it verifies that the module only uses what it declares.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Tessera-kit/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
