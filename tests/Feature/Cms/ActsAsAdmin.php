<?php

namespace Tests\Feature\Cms;

use App\Models\User;

trait ActsAsAdmin
{
    protected function admin(array $attributes = []): User
    {
        $admin = User::factory()->create($attributes);
        $admin->assignRole('admin');

        return $admin;
    }
}
