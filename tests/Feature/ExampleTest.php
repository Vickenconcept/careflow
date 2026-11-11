<?php

use Inertia\Testing\AssertableInertia as Assert;

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('the landing page receives the pilot contact email', function () {
    config(['careflow.contact_email' => 'pilot@careflow.test']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('contactEmail', 'pilot@careflow.test'));
});
