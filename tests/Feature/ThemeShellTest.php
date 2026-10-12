<?php

test('the app shell loads the brand fonts and bootstraps the theme safely', function () {
    $this->withoutVite();

    $this->get('/login')
        ->assertOk()
        ->assertSee('fonts.googleapis.com/css2', false)
        ->assertSee('Instrument+Serif', false)
        ->assertSee('family=Geist', false)
        ->assertSee('El+Messiri', false)
        ->assertSee("localStorage.getItem('theme') === 'dark'", false)
        ->assertSee('catch', false)
        ->assertSee('data-theme="light"', false);
});
