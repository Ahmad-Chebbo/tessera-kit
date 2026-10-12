# Tessera

A modular kit for Laravel business applications. Install a small base, then add the features you need as modules, the way you add plugins to WordPress, but as normal Composer packages.

```bash
composer create-project tessera/tessera-starter shop --repository-url=https://tessera-kit.github.io/tessera-kit
cd shop && bun install
php artisan module:add sales
bun run build
```

Start from the [starter](https://github.com/Tessera-kit/tessera-starter) to build a multi-company app, or from [fresh](https://github.com/Tessera-kit/tessera-fresh) for a plain one with users and roles. This repository is where Tessera itself is developed.

## The pieces

| Package | Repository | Installed with | What it is |
|---|---|---|---|
| Engine | [tessera-engine](https://github.com/Tessera-kit/tessera-engine) | Composer | Finds, orders and boots modules; `module:*` commands. Works in any Laravel app. |
| Base | [tessera-base](https://github.com/Tessera-kit/tessera-base) | Composer | Auth and Team (users, roles, permissions), plus the shared contracts. Works alone. |
| SaaS | [tessera-saas](https://github.com/Tessera-kit/tessera-saas) | Composer | Company and Subscription: companies, branches, plans. Builds on the base. |
| UI | [tessera-ui](https://github.com/Tessera-kit/tessera-ui) | bun / npm | Vue 3 components, composables and the theme. Standalone. |
| Modules | `tessera-<key>` | Composer | Attendance, Catalog, Customers, Discounts, Expenses, Exports, Inventory, Reports, Sales, Shifts. |
| Starter | [tessera-starter](https://github.com/Tessera-kit/tessera-starter) | `composer create-project` | A Laravel SaaS app with the engine, base, core, UI and application shell. |
| Fresh | [tessera-fresh](https://github.com/Tessera-kit/tessera-fresh) | `composer create-project` | The same without companies and plans: sign-in, users, roles and permissions. |

Every package has a README. They are read-only copies of folders here.

## How the monorepo is laid out

```
packages/engine           Tessera\Engine         the module engine
packages/base             Tessera\Core           contracts + modules/{Auth,Team}
packages/saas             App\Modules\...        modules/{Company,Subscription}
packages/ui               @tessera/tessera-ui   the interface kit (npm)
packages/modules/<key>    App\Modules\<Name>    one folder per feature module
app, resources, routes    the host application (it becomes the starter)
bin/build-starter.php     builds the starter project from the host application
tests/                    core and product tests
```

The host application installs everything from `packages/` through Composer path repositories, exactly as a customer project installs it from the published repository, so what you run here is what users get.

## Work on it

```bash
composer install
cp .env.example .env && php artisan key:generate
bun install
php artisan migrate --seed
bun run dev            # or: bun run build
php artisan test       # all packages' tests
bun run test           # frontend tests
php artisan module:check --strict
```

Rules that keep modules reusable (checked by `module:check`): a module imports another module only when its manifest requires it; contracts replace direct dependencies on optional modules; class names stay under `App\Modules\<Name>\` because the database stores them.

## Upgrading from 1.x

Version 2.0 moved the project to the GitHub organization [Tessera-kit](https://github.com/Tessera-kit) and renamed the packages: the Composer vendor is now `tessera` (`tessera/tessera-engine`, `tessera/tessera-sales`, …), the interface kit is `@tessera/tessera-ui`, and the Composer repository is `https://tessera-kit.github.io/tessera-kit`. Class names, namespaces and the database are unchanged. In an existing project:

```bash
composer config repositories.tessera composer https://tessera-kit.github.io/tessera-kit
composer remove ahmad-chebbo/tessera-engine ahmad-chebbo/tessera-saas   # and every other ahmad-chebbo/* package it requires
composer require tessera/tessera-engine tessera/tessera-saas            # and the modules you use
# in package.json and resources/js, replace @ahmad-chebbo/tessera-ui with @tessera/tessera-ui, pin it to github:Tessera-kit/tessera-ui#v2.0.0
bun install && bun run build
```

Each new package declares that it replaces the old one, so Composer drops the old copies as it installs the new ones.

## Release

Tag the monorepo (`git tag v2.0.0 && git push --tags`). The **Split** workflow copies each package to its repository with the same tag and publishes the starter; the **Repository** workflow then rebuilds the Composer repository at `https://tessera-kit.github.io/tessera-kit` that lists every package. The **Tests** workflow runs on every push. The workflows need a repository secret `SPLIT_TOKEN` (a token that may push to the package repositories).

## Add a module

```bash
php artisan module:make Invoices --requires=sales    # in a project made from the starter
```

To add it to the monorepo as a package, create `packages/modules/invoices` with a `composer.json` like its neighbours, require it in the root `composer.json`, add its repository to the matrix of `.github/workflows/split.yml` and to `.github/satis.json`.

## License

All rights reserved until a license is added.
