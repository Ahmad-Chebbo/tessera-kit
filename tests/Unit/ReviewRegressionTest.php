<?php

function projectFile(string $relativePath): string
{
    return file_get_contents(frontendPath($relativePath));
}

it('keeps the desktop sidebar on screen in both text directions', function () {
    $sidebar = projectFile('resources/js/Layouts/SidebarLayout.vue');

    expect($sidebar)
        ->not->toContain('lg:translate-x-0')
        ->toContain('max-lg:ltr:-translate-x-full')
        ->toContain('max-lg:rtl:translate-x-full');
});

it('themes raw form fields so they stay readable in dark mode', function () {
    $css = file_get_contents(uiSource().'/theme.css');

    expect($css)->toMatch('/textarea[^{]*\{[^}]*background-color:\s*var\(--field\)[^}]*color:\s*var\(--text\)/s');
});

it('declares a dark colour scheme and pointer cursors for buttons', function () {
    $css = file_get_contents(uiSource().'/theme.css');

    expect($css)
        ->toMatch("/html\[data-theme='dark'\]\s*\{[^}]*color-scheme:\s*dark/s")
        ->toMatch('/button[^{]*\{[^}]*cursor:\s*pointer/s');
});

it('prints the dark theme with the light token values', function () {
    $css = file_get_contents(uiSource().'/theme.css');

    expect(preg_match("/@media print\s*\{\s*html\[data-theme='dark'\]\s*\{(.*?)\}\s*\}/s", $css, $print))->toBe(1);
    preg_match("/:root\s*\{(.*?)\n\}/s", $css, $root);

    preg_match_all('/--([a-z0-9-]+):\s*([^;]+);/', $print[1], $printTokens, PREG_SET_ORDER);
    preg_match_all('/--([a-z0-9-]+):\s*([^;]+);/', $root[1], $rootTokens, PREG_SET_ORDER);
    $light = array_column($rootTokens, 2, 1);

    expect($printTokens)->not->toBeEmpty();
    foreach ($printTokens as [, $name, $value]) {
        expect(trim($value))->toBe(trim($light[$name]), "--{$name} differs between print and light");
    }
});

it('keeps v-else on the category icon fallback', function (string $file) {
    requireInstalled($file);
    expect(projectFile($file))->toContain('<Tag v-else');
})->with(installedFiles([
    'resources/js/Pages/Categories/Create.vue',
    'resources/js/Pages/Categories/Edit.vue',
    'resources/js/Pages/Categories/Index.vue',
], ''));

it('does not cover POS product photos with an opaque hover overlay', function () {
    expect(projectFile('resources/js/Components/POS/ProductCard.vue'))->not->toContain('group-hover:bg-gold-deep');
})->skip(fn () => ! is_file(frontendPath('resources/js/Components/POS/ProductCard.vue')), 'Sales is not installed');

it('reads the stored locale without throwing when storage is blocked', function () {
    expect(file_get_contents(uiSource().'/i18n.js'))->toContain('try {');
});
