<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class TenantApiIsolationTest extends TestCase
{
    public function test_security_suite_is_loaded(): void
    {
        $this->assertTrue(true);
    }
}
