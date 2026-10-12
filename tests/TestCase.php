<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tracker.enabled' => true,
            // Tests seed raw activity rows, not daily rollups.
        ]);
    }
}
