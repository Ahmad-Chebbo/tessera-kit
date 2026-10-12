/**
 * Indexes the page components found by import.meta.glob by their Inertia name, the path after "/Pages/"
 * without the extension, so a page resolves the same wherever its module keeps it.
 */
export function indexPages(...globs) {
    const pages = {};

    for (const glob of globs) {
        for (const [path, loader] of Object.entries(glob)) {
            const name = path.replace(/^.*\/Pages\//, '').replace(/\.vue$/, '');

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
