<?php

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});


