<?php

namespace Tests\Feature;

use Tests\TestCase;

class RequestCorrelationIdTest extends TestCase
{
    public function test_it_generates_a_request_id_when_none_is_supplied(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Request-ID');

        $requestId = $response->headers->get('X-Request-ID');

        $this->assertIsString($requestId);
        $this->assertNotEmpty($requestId);
        $this->assertMatchesRegularExpression(
            '/\A[a-f0-9-]{36}\z/i',
            $requestId,
        );
    }

    public function test_it_preserves_a_valid_supplied_request_id(): void
    {
        $requestId = 'careerbotz-test-123';

        $response = $this->withHeader(
            'X-Request-ID',
            $requestId,
        )->get('/');

        $response->assertOk();
        $response->assertHeader('X-Request-ID', $requestId);
    }

    public function test_it_replaces_an_invalid_supplied_request_id(): void
    {
        $response = $this->withHeader(
            'X-Request-ID',
            "invalid request id\r\ninjected",
        )->get('/');

        $response->assertOk();
        $response->assertHeader('X-Request-ID');

        $requestId = $response->headers->get('X-Request-ID');

        $this->assertMatchesRegularExpression(
            '/\A[a-f0-9-]{36}\z/i',
            $requestId,
        );
    }
}
