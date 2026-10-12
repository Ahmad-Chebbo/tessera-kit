# Tessera Attendance

Staff check-in and check-out, times a manager can correct, absences with excuses, and an hours report per person and period.

A module for [Tessera](https://github.com/Tessera-kit/tessera-kit). It needs a project made from the [Tessera starter](https://github.com/Tessera-kit/tessera-starter) (the engine, the core and the interface kit).

## Install

```bash
php artisan module:add attendance
bun run build
```

`module:add` requires `tessera/tessera-attendance`, runs the migrations and gives the roles of every company the new permissions. Without it:

```bash
composer config repositories.tessera composer https://tessera-kit.github.io/tessera-kit
composer require tessera/tessera-attendance
php artisan migrate
php artisan modules:sync
bun run build
```

## What it needs

- Other Tessera modules: `shifts`. Composer installs them for you.

## What it adds

| Plan feature | Permissions |
|---|---|
| Attendance | view attendance, manage attendance, record attendance |

Menu entries: Attendance history.

- Exports two reports to Excel and PDF through the Exports module: attendance and attendance history.
- Has API routes (`/api/v1`) so a mobile app can check people in and out.

## Notes

- A user needs the `record attendance` permission to check themselves in; `manage attendance` lets a manager edit anyone's times.

## Turn it off without removing it

Set `MODULES_ENABLED` in `.env` to the modules you want, for example `MODULES_ENABLED=catalog,sales`. Core modules are always on. A module that is left out disappears from the menu, the routes and the plan features.

## Develop

The module lives in `packages/modules/attendance` of the monorepo, with its tests in `Tests/Feature`. Run `php artisan module:check` after changing it: it verifies that the module only uses what it declares.

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Tessera-kit/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
