<?php

use App\Modules\Team\Models\User;

test('public registration is disabled', function () {
    $this->get('/register')->assertNotFound();
});

test('visitors cannot create accounts through the registration endpoint', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});
