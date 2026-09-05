<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use VitaminD\Core\Events\UserChanged;
use VitaminD\Core\Events\UserChanging;
use VitaminD\Core\Events\UserRemoved;
use VitaminD\Core\Events\UserRemoving;
use VitaminD\Core\Events\UserStored;
use VitaminD\Core\Events\UserStoring;
use VitaminD\PluginSdk\RegisterRole;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_users_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/admin/users');

        $response->assertStatus(404);
    }

    public function test_admin_can_access_admin_users_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->delete("/admin/users/{$admin->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($admin)->delete("/admin/users/{$other->id}");

        $response->assertRedirect(route('users'));
        $this->assertDatabaseMissing('users', ['id' => $other->id]);
    }

    public function test_admin_can_search_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['name' => 'Findme Search', 'email' => 'findme@example.com']);
        User::factory()->create(['name' => 'Someone Else', 'email' => 'else@example.com']);

        $response = $this->actingAs($admin)->get('/admin/users/json?query=Findme');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['email' => 'findme@example.com']);
    }

    public function test_admin_created_user_is_app_layer_instance_with_workspace_mixin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'password',
        ]);

        $created = User::where('email', 'new-user@example.com')->firstOrFail();

        $this->assertInstanceOf(User::class, $created);
        $this->assertTrue(method_exists($created, 'workspaces'));
        $this->assertNotNull($created->workspaces());
    }

    public function test_admin_creating_user_dispatches_storing_and_stored_events(): void
    {
        Event::fake([UserStoring::class, UserStored::class]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New User',
            'email' => 'storing-event@example.com',
            'password' => 'password',
        ]);

        Event::assertDispatched(UserStoring::class, fn (UserStoring $event) => $event->input['email'] === 'storing-event@example.com');
        Event::assertDispatched(UserStored::class, fn (UserStored $event) => $event->user->email === 'storing-event@example.com');
    }

    public function test_admin_updating_user_dispatches_changing_and_changed_events(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create(['is_admin' => false]);
        Event::fake([UserChanging::class, UserChanged::class]);

        $this->actingAs($admin)->patch("/admin/users/{$other->id}", [
            'name' => 'Updated Name',
            'email' => $other->email,
        ]);

        Event::assertDispatched(UserChanging::class, fn (UserChanging $event) => $event->user->id === $other->id && $event->input['name'] === 'Updated Name');
        Event::assertDispatched(UserChanged::class, fn (UserChanged $event) => $event->user->id === $other->id && $event->user->name === 'Updated Name');
    }

    public function test_admin_deleting_user_dispatches_removing_and_removed_events(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create(['is_admin' => false]);
        Event::fake([UserRemoving::class, UserRemoved::class]);

        $this->actingAs($admin)->delete("/admin/users/{$other->id}");

        Event::assertDispatched(UserRemoving::class, fn (UserRemoving $event) => $event->user->id === $other->id);
        Event::assertDispatched(UserRemoved::class, fn (UserRemoved $event) => $event->user->id === $other->id);
    }

    /**
     * Regression coverage for CreateUser/UpdateUser's rework (see design.md
     * Decision 7): `is_admin` is now a plain, independent boolean input, no
     * longer derived from a `role` string.
     */
    public function test_creating_a_user_with_is_admin_true_sets_is_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
            'password' => 'password',
            'is_admin' => true,
        ]);

        $this->assertDatabaseHas('users', ['email' => 'new-admin@example.com', 'is_admin' => true]);
    }

    public function test_creating_a_user_without_is_admin_leaves_it_false(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Plain User',
            'email' => 'new-plain-user@example.com',
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'new-plain-user@example.com', 'is_admin' => false]);
    }

    public function test_creating_a_user_with_an_unregistered_role_key_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Invalid Role',
            'email' => 'invalid-role@example.com',
            'password' => 'password',
            'role' => ['not-a-registered-role'],
        ]);

        $response->assertSessionHasErrors('role.0');
        $this->assertDatabaseMissing('users', ['email' => 'invalid-role@example.com']);
    }

    public function test_updating_a_user_to_is_admin_true_sets_is_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)->patch("/admin/users/{$other->id}", [
            'name' => $other->name,
            'email' => $other->email,
            'is_admin' => true,
        ]);

        $this->assertTrue($other->fresh()->is_admin);
    }

    public function test_updating_a_user_to_is_admin_false_unsets_is_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch("/admin/users/{$other->id}", [
            'name' => $other->name,
            'email' => $other->email,
            'is_admin' => false,
        ]);

        $this->assertFalse($other->fresh()->is_admin);
    }

    /**
     * Coverage for the reworked `role` field (design.md Decision 7):
     * multi-select, registry-backed, real `user_roles` assignment,
     * independent of `is_admin`.
     */
    public function test_creating_a_user_with_multiple_roles_assigns_each_one(): void
    {
        RegisterRole::make('gudang')->title('Gudang')->register('test-plugin');
        RegisterRole::make('sales')->title('Sales')->register('test-plugin');
        $keys = collect(RegisterRole::get())->keys()
            ->filter(fn (string $key) => str_ends_with($key, '.gudang') || str_ends_with($key, '.sales'))
            ->values()
            ->all();

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Multi Role',
            'email' => 'multi-role@example.com',
            'password' => 'password',
            'role' => $keys,
        ])->assertRedirect();

        $created = User::where('email', 'multi-role@example.com')->firstOrFail();

        foreach ($keys as $key) {
            $this->assertDatabaseHas('user_roles', ['user_id' => $created->id, 'role' => $key]);
        }
    }

    public function test_creating_a_user_with_roles_and_is_admin_together_applies_both(): void
    {
        RegisterRole::make('finance')->title('Finance')->register('test-plugin');
        $key = collect(RegisterRole::get())->keys()->first(fn (string $k) => str_ends_with($k, '.finance'));

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Admin And Role',
            'email' => 'admin-and-role@example.com',
            'password' => 'password',
            'is_admin' => true,
            'role' => [$key],
        ])->assertRedirect();

        $created = User::where('email', 'admin-and-role@example.com')->firstOrFail();

        $this->assertTrue($created->is_admin);
        $this->assertDatabaseHas('user_roles', ['user_id' => $created->id, 'role' => $key]);
    }

    public function test_updating_a_user_role_selection_is_synced_not_merely_added(): void
    {
        RegisterRole::make('one')->title('One')->register('test-plugin');
        RegisterRole::make('two')->title('Two')->register('test-plugin');
        $keyOne = collect(RegisterRole::get())->keys()->first(fn (string $k) => str_ends_with($k, '.one'));
        $keyTwo = collect(RegisterRole::get())->keys()->first(fn (string $k) => str_ends_with($k, '.two'));

        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)->patch("/admin/users/{$other->id}", [
            'name' => $other->name,
            'email' => $other->email,
            'role' => [$keyOne],
        ])->assertRedirect();

        $this->assertDatabaseHas('user_roles', ['user_id' => $other->id, 'role' => $keyOne]);

        // Resubmitting with a different set replaces it — the deselected
        // role is removed, not left behind.
        $this->actingAs($admin)->patch("/admin/users/{$other->id}", [
            'name' => $other->name,
            'email' => $other->email,
            'role' => [$keyTwo],
        ])->assertRedirect();

        $this->assertDatabaseMissing('user_roles', ['user_id' => $other->id, 'role' => $keyOne]);
        $this->assertDatabaseHas('user_roles', ['user_id' => $other->id, 'role' => $keyTwo]);
    }
}
