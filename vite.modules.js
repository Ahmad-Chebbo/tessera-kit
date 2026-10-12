import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const root = (path) => fileURLToPath(new URL(path, import.meta.url));

/**
 * Aliases for `@modules/<Name>/...` imports. Modules in app/Modules resolve through the `@modules` folder; a module that
 * was installed with Composer lives in vendor/ahmad-chebbo/<key>, so it gets an alias of its own, found through the
 * namespace its composer.json declares.
 */
export function moduleAliases() {
    const aliases = [];
    const packages = root('./vendor/ahmad-chebbo');

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
