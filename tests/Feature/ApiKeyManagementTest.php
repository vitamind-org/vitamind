<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use VitaminD\Core\Events\ApiKeyRemoved;
use VitaminD\Core\Events\ApiKeyRemoving;
use VitaminD\Core\Events\ApiKeyStored;
use VitaminD\Core\Events\ApiKeyStoring;

class ApiKeyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_api_keys_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings/api-keys');

        $response->assertOk();
    }

    public function test_authenticated_user_can_create_api_key(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/settings/api-keys', [
            'name' => 'Test Key',
            'permission' => 'read',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'Test Key']);
    }

    public function test_authenticated_user_can_delete_own_api_key(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('to-delete');

        $response = $this->actingAs($user)->delete("/settings/api-keys/{$token->accessToken->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_creating_api_key_dispatches_storing_and_stored_events(): void
    {
        $user = User::factory()->create();
        Event::fake([ApiKeyStoring::class, ApiKeyStored::class]);

        $this->actingAs($user)->post('/settings/api-keys', [
            'name' => 'Event Test Key',
            'permission' => 'read',
        ]);

        Event::assertDispatched(ApiKeyStoring::class, fn (ApiKeyStoring $event) => $event->user->id === $user->id && $event->input['name'] === 'Event Test Key');
        Event::assertDispatched(ApiKeyStored::class, fn (ApiKeyStored $event) => $event->token->name === 'Event Test Key');
    }

    public function test_deleting_api_key_dispatches_removing_and_removed_events(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('to-delete');
        Event::fake([ApiKeyRemoving::class, ApiKeyRemoved::class]);

        $this->actingAs($user)->delete("/settings/api-keys/{$token->accessToken->id}");

        Event::assertDispatched(ApiKeyRemoving::class, fn (ApiKeyRemoving $event) => $event->apiKey->id === $token->accessToken->id);
        Event::assertDispatched(ApiKeyRemoved::class, fn (ApiKeyRemoved $event) => $event->apiKey->id === $token->accessToken->id);
    }
}
