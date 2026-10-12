<?php

it('sends visitors to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
});
