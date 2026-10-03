<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_are_redirected_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_users_without_an_admin_role_are_forbidden(): void
    {
        $this->seed(RoleSeeder::class);
        $user = $this->createUser();

        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_moderators_are_not_allowed_into_the_admin_panel(): void
    {
        $this->seed(RoleSeeder::class);
        $user = $this->createUser();
        $user->assignRole('Moderator');

        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_admin_and_super_admin_can_open_the_admin_panel(): void
    {
        $this->seed(RoleSeeder::class);

        foreach (['Admin', 'Super Admin'] as $role) {
            $user = $this->createUser();
            $user->assignRole($role);

            $this->actingAs($user)->get('/admin/dashboard')->assertOk();
        }
    }

    public function test_landing_page_replaces_the_old_admin_home_url(): void
    {
        $this->get('/home')->assertNotFound();
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_role_seeder_fills_missing_roles_without_duplicates(): void
    {
        Role::query()->create(['name' => 'Admin', 'guard_name' => 'web']);
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseHas('roles', [
            'name' => 'Super Admin',
            'guard_name' => 'web',
        ]);
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Admin access test',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
        ]);
    }
}
