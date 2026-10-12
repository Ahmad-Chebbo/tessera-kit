function pageName(path) {
    return path.replace(/^.*\/Pages\//, '').replace(/\.vue$/, '');
}

/**
 * Indexes the page components found by import.meta.glob by their Inertia name, the path after "/Pages/" without the
 * extension, so a page resolves the same wherever its module keeps it.
 *
 * The first glob is the project's own pages. A page there replaces the page of a module with the same name, which is how
 * `php artisan module:publish <key> --pages` lets a project change a screen without taking over the module. Two modules
 * that provide the same page are a mistake and throw.
 */
export function indexPages(projectPages, ...modulePages) {
    const pages = {};

    for (const [path, loader] of Object.entries(projectPages)) {
        pages[pageName(path)] = loader;
    }

    const own = new Set(Object.keys(pages));

    for (const glob of modulePages) {
        for (const [path, loader] of Object.entries(glob)) {
            const name = pageName(path);

            if (own.has(name)) {
                continue;
            }

            if (pages[name]) {
                throw new Error(`Two files provide the page "${name}": ${path}`);
            }

            pages[name] = loader;
        }
    }

    return pages;
}

export function resolvePage(pages, name) {
    const loader = pages[name];

    if (!loader) {
        throw new Error(`Page not found: ${name}`);
    }

    return typeof loader === 'function' ? loader() : loader;
}
