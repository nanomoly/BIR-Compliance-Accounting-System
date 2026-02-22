<?php

test('redirects guests to login from home', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});