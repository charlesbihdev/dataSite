<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_profile(): void
    {
        $this->get('/admin/profile')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_can_view_profile_page(): void
    {
        $admin = $this->actingAsAdmin();

        $this->get('/admin/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/profile')
                ->has('profile', fn ($prop) => $prop
                    ->where('name', $admin->name)
                    ->where('email', $admin->email)
                    ->where('phone', $admin->phone)
                    ->where('username', $admin->username)
                    ->etc()
                )
                ->has('passwordRules')
            );
    }

    public function test_admin_can_update_profile_details(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->patch('/admin/profile', [
            'name' => 'Updated Admin Name',
            'email' => 'updated-admin@datasite.com',
            'phone' => '+233241234567',
            'username' => 'new_admin_user',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertSame('Updated Admin Name', $admin->name);
        $this->assertSame('updated-admin@datasite.com', $admin->email);
        $this->assertSame('+233241234567', $admin->phone);
        $this->assertSame('new_admin_user', $admin->username);
    }

    public function test_admin_email_and_phone_must_be_unique(): void
    {
        Admin::create([
            'name' => 'Another Admin',
            'email' => 'other@datasite.com',
            'phone' => '+233249999999',
            'username' => 'other_user',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        $this->actingAsAdmin();

        $response = $this->from('/admin/profile')->patch('/admin/profile', [
            'name' => 'Attempt Duplicate',
            'email' => 'other@datasite.com',
            'phone' => '+233249999999',
            'username' => 'other_user',
        ]);

        $response->assertRedirect('/admin/profile');
        $response->assertSessionHasErrors(['email', 'phone', 'username']);
    }

    public function test_admin_can_change_password_with_valid_current_password(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->put('/admin/profile/password', [
            'current_password' => 'secret123',
            'password' => 'NewSecurePassword!2026',
            'password_confirmation' => 'NewSecurePassword!2026',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword!2026', $admin->password));

        // Verify admin can attempt login with new password
        $this->assertTrue(Auth::guard('admin')->attempt([
            'email' => $admin->email,
            'password' => 'NewSecurePassword!2026',
        ]));
    }

    public function test_admin_cannot_change_password_with_incorrect_current_password(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->from('/admin/profile')->put('/admin/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'NewSecurePassword!2026',
            'password_confirmation' => 'NewSecurePassword!2026',
        ]);

        $response->assertRedirect('/admin/profile');
        $response->assertSessionHasErrors('current_password');

        $admin->refresh();
        $this->assertTrue(Hash::check('secret123', $admin->password));
    }

    public function test_admin_password_confirmation_must_match(): void
    {
        $this->actingAsAdmin();

        $response = $this->from('/admin/profile')->put('/admin/profile/password', [
            'current_password' => 'secret123',
            'password' => 'NewSecurePassword!2026',
            'password_confirmation' => 'Mismatch123!',
        ]);

        $response->assertRedirect('/admin/profile');
        $response->assertSessionHasErrors('password');
    }
}

