<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // All Laravel tests run without outbound HTTP. Each integration test
        // defines its own response so endpoint-specific behavior is preserved.
        Http::preventStrayRequests();
    }
}
