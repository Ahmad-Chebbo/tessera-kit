<?php

function uiFile(string $path): string
{
    if ($path === 'resources/css/app.css') {
        return file_get_contents(uiSource().'/theme.css');
    }

    return file_get_contents(frontendPath($path));
}

it('defines a slim scrollbar utility that only shows on hover', function () {
    $css = uiFile('resources/css/app.css');

    expect($css)
        ->toContain('@utility scroll-thin')
        ->toContain('scrollbar-width: thin')
        ->toMatch('/scroll-thin[^}]*hover[^}]*scrollbar-color/s');
});

it('gives the sidebar navigation a scroll area with fade indicators', function () {
    $sidebar = uiFile('resources/js/Layouts/SidebarLayout.vue');

    expect($sidebar)
        ->toContain("import ScrollArea from '@tessera/tessera-ui/Components/ScrollArea.vue'")
        ->toContain('<ScrollArea')
        ->not->toContain('overflow-y-auto px-3 py-4');
});

it('uses icon buttons instead of text links for edit and delete actions', function () {
    $offenders = [];
    $pages = frontendFiles('Pages');

    foreach ($pages as $file) {
        if ($file->getExtension() === 'vue' && preg_match("/>\s*\{\{\s*t\('common\.(edit|delete)'\)\s*\}\}\s*</", file_get_contents($file->getPathname()))) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});

it('has no native select elements left', function () {
    $offenders = [];
    $files = frontendFiles();

    foreach ($files as $file) {
        if ($file->getExtension() === 'vue' && str_contains(file_get_contents($file->getPathname()), '<select')) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});

it('offers a table and grid view on every entity list page', function (string $page) {
    requireInstalled($page);
    $source = preg_replace('/<!--.*?-->/s', '', uiFile("resources/js/Pages/{$page}"));

    expect($source)
        ->toContain('<ViewToggle')
        ->toContain("viewMode === 'table'")
        ->toContain('<GridCard');
})->with(installedFiles([
    'Products/Index.vue',
    'Categories/Index.vue',
    'Customers/Index.vue',
    'Suppliers/Index.vue',
    'Users/Index.vue',
    'Branches/Index.vue',
    'Locations/Index.vue',
    'Units/Index.vue',
    'Admin/Companies/Index.vue',
    'Orders/Index.vue',
    'Purchases/Index.vue',
    'Inventory/Index.vue',
    'Inventory/Movements.vue',
], 'Pages/'));

it('uses filter tabs instead of dropdowns for status, category, branch and role filters', function (string $page, string $model) {
    requireInstalled($page);
    $source = uiFile("resources/js/Pages/{$page}");

    expect($source)
        ->toContain("<FilterTabs v-model=\"{$model}\"")
        ->not->toContain("<Select\n                    v-model=\"{$model}\"")
        ->not->toContain("<Select v-model=\"{$model}\"");
})->with(installedFiles([
    ['Products/Index.vue', 'categoryId'],
    ['Products/Index.vue', 'status'],
    ['Customers/Index.vue', 'status'],
    ['Users/Index.vue', 'branchId'],
    ['Users/Index.vue', 'role'],
    ['Orders/Index.vue', 'status'],
    ['Purchases/Index.vue', 'status'],
    ['Inventory/Index.vue', 'locationId'],
    ['Inventory/Movements.vue', 'type'],
], 'Pages/'));

it('mounts the toast container in every layout and the login page', function (string $file) {
    requireInstalled($file);
    expect(uiFile($file))->toContain('<ToastContainer />');
})->with(installedFiles([
    'resources/js/Layouts/SidebarLayout.vue',
    'resources/js/Layouts/GuestLayout.vue',
    'resources/js/Pages/Auth/Login.vue',
], ''));

it('bridges Inertia errors to toasts at app start', function () {
    expect(uiFile('resources/js/app.js'))->toContain('registerInertiaToasts(router');
});

it('never calls the native browser confirm dialog', function () {
    $offenders = [];
    $files = frontendFiles();

    foreach ($files as $file) {
        if (! in_array($file->getExtension(), ['vue', 'js'], true) || str_contains($file->getPathname(), '__tests__') || $file->getFilename() === 'useConfirm.js') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        if (preg_match('/window\.confirm\(|(?<!await )(?<!function )(?<![\w.])confirm\(/', $source)) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});

it('mounts the confirm dialog in every app layout', function (string $file) {
    requireInstalled($file);
    expect(uiFile($file))->toContain('<ConfirmDialog />');
})->with(installedFiles([
    'resources/js/Layouts/SidebarLayout.vue',
    'resources/js/Layouts/GuestLayout.vue',
], ''));

it('renders every table through the DataTable component', function () {
    $offenders = [];
    $files = frontendFiles();

    foreach ($files as $file) {
        if ($file->getExtension() !== 'vue' || $file->getFilename() === 'DataTable.vue') {
            continue;
        }

        if (str_contains(file_get_contents($file->getPathname()), '<table')) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});

it('renders list page grids through CardGrid', function (string $page) {
    requireInstalled($page);
    $source = preg_replace('/<!--.*?-->/s', '', uiFile("resources/js/Pages/{$page}"));

    expect($source)
        ->toContain('<CardGrid')
        ->toContain('<DataTable')
        ->not->toContain('grid grid-cols-1 gap-4');
})->with(installedFiles([
    'Products/Index.vue',
    'Categories/Index.vue',
    'Customers/Index.vue',
    'Suppliers/Index.vue',
    'Users/Index.vue',
    'Branches/Index.vue',
    'Locations/Index.vue',
    'Units/Index.vue',
    'Admin/Companies/Index.vue',
    'Orders/Index.vue',
    'Purchases/Index.vue',
    'Inventory/Index.vue',
    'Inventory/Movements.vue',
], 'Pages/'));

it('only uses confirm and toast helpers on pages rendered under a container host', function () {
    $offenders = [];
    $pages = frontendFiles('Pages');

    foreach ($pages as $file) {
        if ($file->getExtension() !== 'vue') {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        $usesHelpers = str_contains($source, 'useConfirm') || str_contains($source, 'useToast');
        $hasHost = preg_match('/<(SidebarLayout|GuestLayout)\b/', $source)
            || (str_contains($source, '<ToastContainer') && str_contains($source, '<ConfirmDialog'));

        if ($usesHelpers && ! $hasHost) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});

it('keeps an empty state on the dashboard low stock table', function () {
    expect(uiFile('resources/js/Widgets/LowStock.vue'))->toContain(":empty-title=\"t('dashboard.noLowStock')\"");
})->skip(fn () => ! is_file(frontendPath('resources/js/Widgets/LowStock.vue')), 'Inventory is not installed');

it('lets the confirm dialog default text explain deletes instead of appending question marks', function (string $page) {
    requireInstalled($page);
    expect(uiFile($page))->not->toMatch("/confirm\\.delete\\(t\\('common\\.confirm(?:Delete)?'\\) \\+ '\\?'\\)/");
})->with(installedFiles([
    'resources/js/Pages/Admin/Companies/Index.vue',
    'resources/js/Pages/Branches/Index.vue',
    'resources/js/Pages/Categories/Index.vue',
    'resources/js/Pages/ExchangeRates/Index.vue',
    'resources/js/Pages/Locations/Index.vue',
    'resources/js/Pages/Products/Index.vue',
    'resources/js/Pages/Suppliers/Index.vue',
], ''));

it('shows charts on the dashboard and every report', function (string $page, array $components) {
    requireInstalled($page);
    $source = uiFile("resources/js/Pages/{$page}");

    foreach ($components as $component) {
        expect($source)->toContain("<{$component}");
    }
})->with(installedFiles([
    ['Dashboard.vue', ['TrendChart', 'DoughnutChart', 'BarChart', 'FilterTabs']],
    ['Reports/Sales.vue', ['TrendChart', 'DoughnutChart', 'BarChart']],
    ['Reports/Items.vue', ['DoughnutChart', 'BarChart']],
    ['Reports/Profit.vue', ['LineChart', 'BarChart']],
], 'Pages/'));

it('translates every analytics and chart label in English and Arabic', function () {
    $keys = [];
    $files = [
        'resources/js/Pages/Dashboard.vue',
        'resources/js/Pages/Reports/Sales.vue',
        'resources/js/Pages/Reports/Items.vue',
        'resources/js/Pages/Reports/Profit.vue',
        'resources/js/Components/ChartCard.vue',
        'resources/js/Components/DoughnutChart.vue',
    ];

    foreach (array_filter($files, fn (string $file): bool => is_file(frontendPath($file))) as $file) {
        preg_match_all("/t\('((?:analytics|charts)\.[A-Za-z]+)'/", uiFile($file), $matches);
        $keys = array_merge($keys, $matches[1]);
    }

    $missing = [];
    foreach (['en', 'ar'] as $locale) {
        $messages = frontendMessages($locale);

        foreach (array_unique($keys) as $key) {
            [$section, $name] = explode('.', $key);

            if (! isset($messages[$section][$name])) {
                $missing[] = "{$locale}:{$key}";
            }
        }
    }

    expect($keys)->not->toBeEmpty()
        ->and($missing)->toBe([]);
});

it('uses the themed date picker instead of native date inputs', function () {
    $offenders = [];
    $files = frontendFiles();

    foreach ($files as $file) {
        if ($file->getExtension() === 'vue' && preg_match('/type="(date|datetime-local|time)"/', file_get_contents($file->getPathname()))) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});
