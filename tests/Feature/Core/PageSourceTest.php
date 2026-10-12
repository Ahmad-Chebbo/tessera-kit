<?php

use Tessera\Engine\PageSource;

it('finds a page that lives in a module folder, relative to the project', function () {
    expect(app(PageSource::class)->pathFor('Auth/Login'))->toEndWith('/Resources/js/Pages/Auth/Login.vue')->not->toStartWith('/');
});

it('returns nothing for a page no folder has', function () {
    expect(app(PageSource::class)->pathFor('Nope/Missing'))->toBeNull();
});

it('lets every page render without a running Vite dev server', function () {
    $this->get(route('login'))->assertOk();
});
