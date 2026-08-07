<?php

namespace Tests\Feature;

use App\Actions\WhatsApp\AddNumber;
use App\Enums\WaInstanceState;
use App\Enums\WaNumberStatus;
use App\Models\User;
use App\Models\WaInstance;
use App\Models\WaNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use VitaminD\Plugins\Workspace\Actions\Workspaces\CreateWorkspace;
use VitaminD\Plugins\Workspace\Models\Workspace;

class WaNumberManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.waini_provisioner.base_url' => 'https://waini.test/provisioner',
            'services.waini_provisioner.username' => 'wakuwaku',
            'services.waini_provisioner.password' => 'test-password',
        ]);
    }

    private function workspaceFor(User $user, string $name): Workspace
    {
        return app(CreateWorkspace::class)->create($user, ['name' => $name]);
    }

    private function fakeGowaAndProvisioner(): void
    {
        Http::fake([
            'waini.test/provisioner/instances' => Http::response([
                'status' => 'active',
                'base_url' => 'https://waini.example.com/w/acme',
                'basic_auth_username' => 'wa',
                'basic_auth_password' => 'secret',
            ], 200),
            'waini.example.com/*/devices' => Http::response(['results' => []], 200),
        ]);
    }

    public function test_add_number_lazily_provisions_the_workspace_instance(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceFor($user, 'acme-corp');
        $this->fakeGowaAndProvisioner();

        $response = $this->actingAs($user)->post('/settings/whatsapp-numbers');

        $response->assertRedirect(route('whatsapp-numbers'));
        $this->assertDatabaseHas('wa_instances', ['workspace_id' => $workspace->id]);
        $this->assertSame(1, WaNumber::query()->count());
    }

    public function test_second_number_reuses_the_existing_instance(): void
    {
        $user = User::factory()->create();
        $this->workspaceFor($user, 'acme-corp');
        $this->fakeGowaAndProvisioner();

        $this->actingAs($user)->post('/settings/whatsapp-numbers');
        $this->actingAs($user)->post('/settings/whatsapp-numbers');

        $this->assertSame(1, WaInstance::query()->count());
        $this->assertSame(2, WaNumber::query()->count());
    }

    public function test_numbers_are_only_visible_within_their_owning_workspace(): void
    {
        $userA = User::factory()->create();
        $workspaceA = $this->workspaceFor($userA, 'workspace-a');

        $userB = User::factory()->create();
        $workspaceB = $this->workspaceFor($userB, 'workspace-b');

        $this->fakeGowaAndProvisioner();

        $this->actingAs($userA);
        $numberA = app(AddNumber::class)->handle($workspaceA);

        $this->actingAs($userB);
        $numberB = app(AddNumber::class)->handle($workspaceB);

        // Workspace A's user only sees their own number.
        $response = $this->actingAs($userA)->get('/settings/whatsapp-numbers');
        $response->assertInertia(fn ($page) => $page
            ->has('numbers', 1)
            ->where('numbers.0.id', $numberA->id)
        );

        // Workspace B's user cannot reach workspace A's number by id.
        $this->actingAs($userB)->get("/settings/whatsapp-numbers/{$numberA->id}/status")->assertNotFound();
        $this->actingAs($userB)->delete("/settings/whatsapp-numbers/{$numberA->id}")->assertNotFound();

        $this->assertNotSame($numberA->wa_instance_id, $numberB->wa_instance_id);
    }

    public function test_unlink_logs_out_the_device_and_keeps_the_record(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceFor($user, 'acme-corp');
        $instance = WaInstance::create([
            'workspace_id' => $workspace->id,
            'base_url' => 'https://waini.example.com/w/acme',
            'basic_auth_username' => 'wa',
            'basic_auth_password' => 'secret',
            'state' => WaInstanceState::Active,
        ]);
        $this->actingAs($user);
        $number = WaNumber::create([
            'wa_instance_id' => $instance->id,
            'device_id' => 'device-1',
            'status' => WaNumberStatus::LoggedIn,
        ]);

        Http::fake(['waini.example.com/*/logout' => Http::response(null, 200)]);

        $response = $this->actingAs($user)->post("/settings/whatsapp-numbers/{$number->id}/unlink");

        $response->assertRedirect(route('whatsapp-numbers'));
        $this->assertDatabaseHas('wa_numbers', ['id' => $number->id, 'status' => WaNumberStatus::Disconnected->value]);
    }

    public function test_delete_removes_the_device_and_the_record(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceFor($user, 'acme-corp');
        $instance = WaInstance::create([
            'workspace_id' => $workspace->id,
            'base_url' => 'https://waini.example.com/w/acme',
            'basic_auth_username' => 'wa',
            'basic_auth_password' => 'secret',
            'state' => WaInstanceState::Active,
        ]);
        $this->actingAs($user);
        $number = WaNumber::create([
            'wa_instance_id' => $instance->id,
            'device_id' => 'device-1',
            'status' => WaNumberStatus::Connected,
        ]);

        Http::fake(['waini.example.com/*/devices/device-1' => Http::response(null, 200)]);

        $response = $this->actingAs($user)->delete("/settings/whatsapp-numbers/{$number->id}");

        $response->assertRedirect(route('whatsapp-numbers'));
        $this->assertDatabaseMissing('wa_numbers', ['id' => $number->id]);
    }

    public function test_status_endpoint_refreshes_and_returns_the_number(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceFor($user, 'acme-corp');
        $instance = WaInstance::create([
            'workspace_id' => $workspace->id,
            'base_url' => 'https://waini.example.com/w/acme',
            'basic_auth_username' => 'wa',
            'basic_auth_password' => 'secret',
            'state' => WaInstanceState::Active,
        ]);
        $this->actingAs($user);
        $number = WaNumber::create([
            'wa_instance_id' => $instance->id,
            'device_id' => 'device-1',
            'status' => WaNumberStatus::Disconnected,
        ]);

        Http::fake(['waini.example.com/*/status' => Http::response([
            'results' => ['is_connected' => true, 'is_logged_in' => true, 'jid' => '628123@s.whatsapp.net'],
        ], 200)]);

        $response = $this->actingAs($user)->get("/settings/whatsapp-numbers/{$number->id}/status");

        $response->assertOk();
        $response->assertJsonPath('status', WaNumberStatus::LoggedIn->value);
        $this->assertDatabaseHas('wa_numbers', ['id' => $number->id, 'jid' => '628123@s.whatsapp.net']);
    }
}
