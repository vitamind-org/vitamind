<?php

namespace VitaminD\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use VitaminD\Core\Http\Middleware\HandleInertiaRequests;
use VitaminD\PluginSdk\RegisterPage;

/**
 * `RegisterPage::toArray()` resolves `href`/`description`/`placement` itself
 * (see `vitamind-plugin-sdk`'s own `RegisterPageTest`); this covers only
 * that `HandleInertiaRequests::share()` passes those fields through to the
 * `pluginPages` Inertia prop unchanged, since it's the one place a plugin's
 * registration actually reaches the frontend.
 */
class HandleInertiaRequestsPluginPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_plugin_pages_prop_carries_resolved_href_description_and_placement(): void
    {
        RegisterPage::make('test_custom_link_page')
            ->title('Test Page')
            ->icon('package')
            ->description('A test page description.')
            ->route('dashboard')
            ->placement('settings')
            ->adminOnly(false)
            ->register();

        $user = User::factory()->create();
        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $shared = app(HandleInertiaRequests::class)->share($request);

        $page = collect($shared['pluginPages'])->firstWhere('key', 'test_custom_link_page');

        $this->assertNotNull($page);
        $this->assertEquals(route('dashboard'), $page['href']);
        $this->assertEquals('A test page description.', $page['description']);
        $this->assertEquals('settings', $page['placement']);
    }
}
