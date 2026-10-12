# Tessera Fresh

A Laravel 13 application with sign-in, registration, password reset, a profile page, and users with roles and permissions, ready for [Tessera](https://github.com/Ahmad-Chebbo/tessera-kit) modules. It has no companies, branches or subscriptions: use the [SaaS starter](https://github.com/Ahmad-Chebbo/tessera-starter) when you need those.

It contains the module engine, the base package (Auth and Team), the interface kit and the application shell (sidebar layout, language and theme toggles).

## Create a project

```bash
composer create-project ahmad-chebbo/tessera-fresh app --repository-url=https://ahmad-chebbo.github.io/tessera-kit
cd app
bun install
```

`create-project` copies `.env.example` to `.env`, generates the key and runs the migrations when the database is reachable. Set `DB_*` in `.env` and run `php artisan migrate` if it was not. Then seed the roles and the first account and build the frontend:

```bash
php artisan db:seed
bun run build      # or: bun run dev
```

Sign in with `admin@app.com` / `password`. The account has the **Owner** role, which holds every permission, so it can manage users and roles from the start. Change the password.

## What you get

- **Users** — list, create, edit and delete users, give each one a role, switch them off.
- **Roles and permissions** — create roles and tick the permissions each one holds. Modules you add declare their own permissions, which appear on the role page.
- **Sign-in** — Sanctum API tokens at `/api/v1/auth/token`, password reset, email verification, profile.

Employee profiles (job title, hire date...) and the activity log are part of the Team module but off here. Turn them on in `config/team.php` (`php artisan vendor:publish --tag=tessera-team-config`) or with `TEAM_EMPLOYEE_PROFILES=true` and `TEAM_ACTIVITY_LOG=true`.

## Add modules

```bash
php artisan module:add expenses
bun run build
```

Modules that work with companies (sales, catalog, inventory...) need the SaaS package, and `module:add` installs it for you, which also turns on branches, plans and the other company features. If you do not want those, start from the SaaS starter instead or write your own modules.

## Write your own module

```bash
php artisan module:make Invoices
php artisan module:check
```

The module appears in `app/Modules/Invoices` with a provider, route, page, translations and a passing test. Permissions it declares show up on the role page. When it is ready for other projects, `php artisan module:package invoices` turns it into a Composer package.

## Layout

- `app/Modules` — your own modules.
- `resources/js` — the application shell: layouts, `app.js`, the page resolver and the translation merge. Modules import the layout as `@/Layouts/SidebarLayout.vue`, so keep it.
- `vite.modules.js` — lets `@modules/<Name>/…` imports reach modules installed in `vendor`, also while `bun run dev` is running.
- `config/tessera.php` / `config/modules.php` / `config/team.php` — optional; the packages provide the defaults.

## Useful settings

- `MODULES_ENABLED` — `*` (default), a comma separated list of module keys, or `none`.
- `MODULES_LAYERS` — `web,api` (default), `web`, or `api` for a headless project.

## Tests

```bash
php artisan test
bun run test
```

## Part of Tessera

This repository is a read-only copy built from the [tessera-kit](https://github.com/Ahmad-Chebbo/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
