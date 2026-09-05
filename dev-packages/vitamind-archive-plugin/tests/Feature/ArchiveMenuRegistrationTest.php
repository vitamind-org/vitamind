<?php

namespace VitaminD\Plugins\Archive\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use VitaminD\Plugins\Workspace\Models\Workspace;

class ArchiveMenuRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedUser(): User
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme-'.Str::random(8)]);
        $workspace->users()->create(['user_id' => $user->id, 'is_default' => true]);
        $user->update(['current_workspace_id' => $workspace->id]);

        return $user;
    }

    public function test_archive_registers_a_main_sidebar_entry_in_the_plugin_pages_prop(): void
    {
        $user = $this->onboardedUser();

        $response = $this->actingAs($user)->get('/archive');

        $response->assertInertia(fn ($page) => $page
            ->where('pluginPages', fn ($pages) => $pages->contains(
                fn ($p) => $p['key'] === 'archive'
                    && $p['title'] === 'Archive'
                    && $p['href'] === route('archive.index')
                    && $p['placement'] === 'main'
                    && $p['admin_only'] === false
            ))
        );
    }
}
