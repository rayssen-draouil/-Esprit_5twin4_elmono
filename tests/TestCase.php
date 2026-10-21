<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // All Laravel tests run without outbound HTTP. Tests may replace this
        // default with endpoint-specific Http::fake() responses as needed.
        Http::preventStrayRequests();
        Http::fake([
            'https://api.brevo.com/**' => Http::response([
                'messageId' => 'ci-test-message',
            ], 201),
        ]);
    }
}
