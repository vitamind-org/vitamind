<?php

namespace Tests\Feature;

use App\Actions\Provisioner\CreateInstance;
use App\Actions\Provisioner\DeleteInstance;
use App\Actions\Provisioner\GetInstanceStatus;
use App\Enums\ProvisionerInstanceStatus;
use App\Enums\WaInstanceState;
use App\Exceptions\WhatsApp\ProvisionerException;
use App\Models\User;
use App\Models\WaInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use VitaminD\Plugins\Workspace\Actions\Workspaces\CreateWorkspace;
use VitaminD\Plugins\Workspace\Models\Workspace;

class ProvisionerClientTest extends TestCase
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

    private function workspace(): Workspace
    {
        $owner = User::factory()->create();

        return app(CreateWorkspace::class)->create($owner, ['name' => 'acme-corp']);
    }

    public function test_create_instance_persists_returned_connection_details(): void
    {
        $workspace = $this->workspace();

        Http::fake([
            'waini.test/provisioner/instances' => Http::response([
                'workspace_id' => (string) $workspace->id,
                'status' => 'active',
                'base_url' => 'https://waini.example.com/w/'.$workspace->id,
                'port' => 4001,
                'basic_auth_username' => 'wa',
                'basic_auth_password' => 'secret',
            ], 200),
        ]);

        $instance = app(CreateInstance::class)->handle($workspace);

        $this->assertSame($workspace->id, $instance->workspace_id);
        $this->assertSame('https://waini.example.com/w/'.$workspace->id, $instance->base_url);
        $this->assertSame('wa', $instance->basic_auth_username);
        $this->assertSame('secret', $instance->basic_auth_password);
        $this->assertSame(WaInstanceState::Active, $instance->state);
        $this->assertNotEmpty($instance->webhook_secret);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://waini.test/provisioner/instances'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('wakuwaku:test-password'));
        });
    }

    public function test_create_instance_is_idempotent_and_updates_the_same_row(): void
    {
        $workspace = $this->workspace();

        Http::fake([
            'waini.test/provisioner/instances' => Http::response([
                'workspace_id' => (string) $workspace->id,
                'status' => 'active',
                'base_url' => 'https://waini.example.com/w/'.$workspace->id,
                'basic_auth_username' => 'wa',
                'basic_auth_password' => 'secret',
            ], 200),
        ]);

        $first = app(CreateInstance::class)->handle($workspace);
        $second = app(CreateInstance::class)->handle($workspace);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->webhook_secret, $second->webhook_secret);
        $this->assertSame(1, WaInstance::where('workspace_id', $workspace->id)->count());
    }

    public function test_create_instance_throws_on_failure(): void
    {
        $workspace = $this->workspace();

        Http::fake(['waini.test/provisioner/instances' => Http::response(['error' => 'port exhaustion'], 500)]);

        $this->expectException(ProvisionerException::class);

        app(CreateInstance::class)->handle($workspace);
    }

    public function test_get_instance_status_returns_absent_without_side_effects(): void
    {
        $workspace = $this->workspace();

        Http::fake(['waini.test/provisioner/instances/*' => Http::response([
            'workspace_id' => (string) $workspace->id,
            'status' => 'absent',
        ], 200)]);

        $status = app(GetInstanceStatus::class)->handle($workspace);

        $this->assertSame(ProvisionerInstanceStatus::Absent, $status);
        $this->assertDatabaseMissing('wa_instances', ['workspace_id' => $workspace->id]);
    }

    public function test_get_instance_status_returns_active(): void
    {
        $workspace = $this->workspace();

        Http::fake(['waini.test/provisioner/instances/*' => Http::response(['status' => 'active'], 200)]);

        $status = app(GetInstanceStatus::class)->handle($workspace);

        $this->assertSame(ProvisionerInstanceStatus::Active, $status);
    }

    public function test_delete_instance_removes_local_record_only_after_remote_success(): void
    {
        $workspace = $this->workspace();
        $instance = WaInstance::create([
            'workspace_id' => $workspace->id,
            'base_url' => 'https://waini.example.com/w/'.$workspace->id,
            'basic_auth_username' => 'wa',
            'basic_auth_password' => 'secret',
            'state' => WaInstanceState::Active,
        ]);

        Http::fake(['waini.test/provisioner/instances/*' => Http::response(null, 204)]);

        app(DeleteInstance::class)->handle($instance);

        $this->assertDatabaseMissing('wa_instances', ['id' => $instance->id]);
    }

    public function test_delete_instance_keeps_local_record_when_remote_delete_fails(): void
    {
        $workspace = $this->workspace();
        $instance = WaInstance::create([
            'workspace_id' => $workspace->id,
            'base_url' => 'https://waini.example.com/w/'.$workspace->id,
            'basic_auth_username' => 'wa',
            'basic_auth_password' => 'secret',
            'state' => WaInstanceState::Active,
        ]);

        Http::fake(['waini.test/provisioner/instances/*' => Http::response(['error' => 'busy'], 500)]);

        $this->expectException(ProvisionerException::class);

        try {
            app(DeleteInstance::class)->handle($instance);
        } finally {
            $this->assertDatabaseHas('wa_instances', ['id' => $instance->id]);
        }
    }
}
