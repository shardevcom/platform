<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Assert that the user is not authenticated.
     *
     * Overrides the default to check the 'web' guard (session) instead of
     * the default guard, which may be changed by auth:sanctum middleware.
     */
    public function assertGuest($guard = 'web')
    {
        return parent::assertGuest($guard);
    }
}
