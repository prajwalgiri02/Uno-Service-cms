<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected bool $seedRoles = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        if ($this->seedRoles && method_exists($this, 'refreshDatabase')) {
            $this->seed(RoleSeeder::class);
        }
    }
}
