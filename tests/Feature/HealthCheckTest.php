<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_the_application_health_check_responds_successfully(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }
}
