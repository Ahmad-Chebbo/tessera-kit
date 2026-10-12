# Tessera Fresh

A Laravel 13 application with sign-in, registration, password reset, a profile page, and users with roles and permissions, ready for [Tessera](https://github.com/Tessera-kit/tessera-kit) modules. It has no tenants, branches or subscriptions: use the [SaaS starter](https://github.com/Tessera-kit/tessera-starter) when you need those.

It contains the module engine, the base package (Auth and Team), the interface kit and the application shell (sidebar layout, language and theme toggles).

## Create a project

```bash
composer create-project tessera/tessera-fresh app --repository-url=https://tessera-kit.github.io/tessera-kit
cd app
bun install
```

`create-project` copies `.env.example` to `.env`, generates the key and runs the migrations when the database is reachable. Set `DB_*` in `.env` and run `php artisan migrate` if it was not. Every migration run also creates the permissions and default roles the installed modules declare. Create the first account and build the frontend:

```bash
php artisan tessera:install     # asks for a name, an email and a password
bun run build                   # or: bun run dev
```

The account has the **Owner** role, which holds every permission, so it can manage users and roles from the start. For a local demo, `php artisan db:seed` creates `admin@app.com` / `password` instead; never use it in production.

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

The feature modules (sales, catalog, inventory...) keep their data per tenant, so `module:add` installs the tenant package for you. Your project then runs as a **single business**: one tenant made on first use with a main branch and its stock location, every user in it, and roles shared by everyone. Branches, stock locations, currencies and a settings page appear, and employee profiles and the activity log switch on. There are no plans and no platform admin. To serve many businesses from one install, add `tessera/tessera-saas` or start from the SaaS starter.

## Change a module

```bash
php artisan module:publish catalog          # take the whole module into app/Modules to edit it
php artisan module:publish catalog --pages  # copy only its Vue pages; they replace the module's own
```

The first stops that module updating through Composer. The second keeps it updating while your pages take over the screens you copied.

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

This repository is a read-only copy built from the [tessera-kit](https://github.com/Tessera-kit/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
