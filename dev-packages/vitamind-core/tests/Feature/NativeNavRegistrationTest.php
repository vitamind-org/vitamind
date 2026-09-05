<?php

namespace VitaminD\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use VitaminD\Plugins\Workspace\Models\Workspace;

/**
 * Verifies the migration of Dashboard/Settings/Admin/footer from hardcoded
 * arrays in `app-sidebar.tsx`/`settings/layout.tsx`/`admin/layout.tsx` onto
 * `RegisterNativeNav` (see design.md's D7) preserves the same visibility
 * rules the old client-side `hidden: !is_admin`/`hasRoute()` checks
 * enforced — now evaluated server-side and excluded from the payload
 * entirely, per the `hidden()` requirement.
 */
class NativeNavRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `/dashboard` redirects to onboarding for a user with no workspace
     * (see `ArchiveMenuRegistrationTest`'s identical helper) — every test
     * here needs a real Inertia response from `/dashboard` to inspect
     * `pluginPages`, so it needs an onboarded user, not a bare factory one.
     */
    private function onboardedUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $workspace = Workspace::create(['name' => 'acme-'.Str::random(8)]);
        $workspace->owner_id = $user->id;
        $workspace->save();
        $workspace->users()->create(['user_id' => $user->id, 'is_default' => true]);
        $user->update(['current_workspace_id' => $workspace->id]);

        return $user;
    }

    public function test_non_admin_users_payload_excludes_admin_gated_native_entries(): void
    {
        $user = $this->onboardedUser(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $pages = collect($page->toArray()['props']['pluginPages']);

            $this->assertNull($pages->firstWhere('key', 'admin'));
            $this->assertNull($pages->firstWhere('key', 'native-users'));
            $this->assertNull($pages->firstWhere('key', 'native-plugins'));
            $this->assertNull($pages->firstWhere('key', 'native-horizon'));
            $this->assertNull($pages->firstWhere('key', 'native-logs'));

            $this->assertNotNull($pages->firstWhere('key', 'native-dashboard'));
            $this->assertNotNull($pages->firstWhere('key', 'settings'));
            $this->assertNotNull($pages->firstWhere('key', 'native-profile'));
            $this->assertNotNull($pages->firstWhere('key', 'native-repository'));
            $this->assertNotNull($pages->firstWhere('key', 'native-documentation'));
        });
    }

    public function test_admin_users_payload_includes_every_native_entry(): void
    {
        $admin = $this->onboardedUser(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $pages = collect($page->toArray()['props']['pluginPages']);

            $this->assertNotNull($pages->firstWhere('key', 'admin'));
            $this->assertNotNull($pages->firstWhere('key', 'native-users'));
            $this->assertNotNull($pages->firstWhere('key', 'native-plugins'));
            $this->assertNotNull($pages->firstWhere('key', 'native-dashboard'));
            $this->assertNotNull($pages->firstWhere('key', 'settings'));

            // Horizon/Logs stay excluded regardless of admin status in this
            // repo — the packages simply aren't installed (Route::has()
            // false), exactly matching the pre-migration hasRoute() checks.
            $this->assertNull($pages->firstWhere('key', 'native-horizon'));
            $this->assertNull($pages->firstWhere('key', 'native-logs'));
        });
    }

    public function test_admin_group_lands_on_the_users_page(): void
    {
        $admin = $this->onboardedUser(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $pages = collect($page->toArray()['props']['pluginPages']);
            $adminEntry = $pages->firstWhere('key', 'admin');

            $this->assertEquals(route('users'), $adminEntry['href']);
            $this->assertEquals('main', $adminEntry['placement']);
            $this->assertNull($adminEntry['group']);
        });
    }

    public function test_settings_group_lands_on_the_profile_page(): void
    {
        $user = $this->onboardedUser(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $pages = collect($page->toArray()['props']['pluginPages']);
            $settingsEntry = $pages->firstWhere('key', 'settings');

            $this->assertEquals(route('profile'), $settingsEntry['href']);
        });
    }

    public function test_footer_entries_render_in_registration_order(): void
    {
        $user = $this->onboardedUser(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(function ($page) {
            $footerKeys = collect($page->toArray()['props']['pluginPages'])
                ->where('placement', 'footer')
                ->pluck('key')
                ->values();

            $this->assertEquals(['native-repository', 'native-documentation'], $footerKeys->all());
        });
    }
}
