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
            'role' => 'user',
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
            'role' => 'user',
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
            'role' => 'user',
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
}
