<?php

namespace Tests;

use App\Models\Admin;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Create a superadmin and authenticate the request on the admin guard.
     */
    protected function actingAsAdmin(): Admin
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'phone' => '+233240000010',
            'email' => 'admin@datasite.com',
            'username' => 'superadmin',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');

        return $admin;
    }
}
