<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('operators can visit the dashboard', function () {
    $operator = User::factory()->operator()->create();
    $this->actingAs($operator);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('players cannot visit the operator dashboard', function () {
    $player = User::factory()->create();
    $this->actingAs($player);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('home'));
});
