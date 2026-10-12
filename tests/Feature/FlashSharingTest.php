<?php

use App\Modules\Team\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('session flash messages are sent as history-safe Inertia flash data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['success' => 'Saved.', 'error' => 'Nope.'])
        ->get('/profile')
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('success', 'Saved.')
            ->hasFlash('error', 'Nope.'));
});

test('flash data is absent when nothing was flashed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile')
        ->assertInertia(fn (Assert $page) => $page
            ->missingFlash('success')
            ->missingFlash('error'));
});

test('flash messages are not placed in regular props, which browsers keep in history', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['success' => 'Saved.'])
        ->get('/profile')
        ->assertInertia(fn (Assert $page) => $page->missing('flash'));
});
