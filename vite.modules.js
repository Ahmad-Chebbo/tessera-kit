import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const root = (path) => fileURLToPath(new URL(path, import.meta.url));

/**
 * Aliases for `@modules/<Name>/...` imports. Modules in app/Modules resolve through the `@modules` folder; a module that
 * was installed with Composer lives in vendor/tessera/<key>, so it gets an alias of its own, found through the
 * namespace its composer.json declares.
 */
export function moduleAliases() {
    const aliases = [];
    const packages = root('./vendor/tessera');

    if (existsSync(packages)) {
        for (const key of readdirSync(packages)) {
            const file = `${packages}/${key}/composer.json`;

            if (!existsSync(file)) {
                continue;
            }

            for (const [namespace, folder] of Object.entries(JSON.parse(readFileSync(file, 'utf8')).autoload?.['psr-4'] ?? {})) {
                const name = namespace.match(/^App\\Modules\\(\w+)\\$/)?.[1];

                if (name) {
                    aliases.push({ find: new RegExp(`^@modules/${name}(?=/|$)`), replacement: `${packages}/${key}/${folder}`.replace(/\/+$/, '') });
                }
            }
        }
    }

    return [...aliases, { find: '@modules', replacement: root('./app/Modules') }];
}

/**
 * Resolves `@modules/<Name>/...` while Vite runs, instead of once at start-up like `moduleAliases()`: a module added
 * with `composer require` or `module:add` works without restarting the dev server. Modules in vendor win over
 * app/Modules, which is the fallback.
 */
export function tesseraModules() {
    let known = moduleAliases().slice(0, -1);

    const lookup = (name) => {
        const find = (aliases) => aliases.find((alias) => alias.find.test(`@modules/${name}`));

        return find(known) ?? find((known = moduleAliases().slice(0, -1)));
    };

    return {
        name: 'tessera-modules',
        enforce: 'pre',
        resolveId(source, importer, options) {
            const match = source.match(/^@modules\/(\w+)(\/.*)?$/);

            if (!match) {
                return null;
            }

            const base = lookup(match[1])?.replacement ?? root(`./app/Modules/${match[1]}`);

            return this.resolve(`${base}${match[2] ?? ''}`, importer, { ...options, skipSelf: true });
        },
    };
}
