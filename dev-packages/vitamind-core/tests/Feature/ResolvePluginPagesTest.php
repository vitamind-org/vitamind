<?php

namespace VitaminD\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\TestCase;
use VitaminD\Core\Actions\Plugins\ResolvePluginPages;
use VitaminD\Core\Http\Middleware\HandleInertiaRequests;
use VitaminD\PluginSdk\RegisterPage;
use VitaminD\PluginSdk\RegisterPageGroup;

/**
 * `RegisterPage`/`RegisterPageGroup` resolve their own fields in isolation
 * (see each SDK's own unit tests); this covers the correlation work
 * `ResolvePluginPages` does across the two registries — group collapsing,
 * `hidden()` exclusion (including the group-hides-its-members cascade), and
 * `order`-based sorting — as it actually reaches the `pluginPages` prop via
 * `HandleInertiaRequests::share()`.
 */
class ResolvePluginPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every test here registers ad-hoc pages/groups directly in its body
     * (the established convention in this file's sibling tests), which
     * `TestCase::setUp()`'s "always-on, non-tabs registration survives
     * flush" restore mechanism cannot distinguish from a real plugin
     * provider's boot-time registration. That's harmless for entries that
     * merely sit inertly in the registry, but several tests here register a
     * page/group whose `hidden()`/`group()` closures are only valid for
     * *this* test's assertions (or deliberately invalid, to prove a throw) —
     * leaking one forward could fail an unrelated later test. Flushing both
     * registries after every test keeps this file's registrations from ever
     * being candidates for that restore in the first place.
     */
    protected function tearDown(): void
    {
        RegisterPage::flush();
        RegisterPageGroup::flush();

        parent::tearDown();
    }

    private function pluginPagesFor(User $user): Collection
    {
        $this->actingAs($user);

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        return collect(app(HandleInertiaRequests::class)->share($request)['pluginPages']);
    }

    public function test_group_collapses_to_one_entry_landing_on_its_first_member(): void
    {
        RegisterPageGroup::make('whatsapp')->title('WhatsApp')->icon('message-circle')->register();

        RegisterPage::make('whatsapp_numbers')->title('My Numbers')->icon('phone')
            ->group('whatsapp')->href('/whatsapp/numbers')->adminOnly(false)->register();
        RegisterPage::make('whatsapp_contacts')->title('Contacts')->icon('users')
            ->group('whatsapp')->href('/whatsapp/contacts')->adminOnly(false)->register();

        $pages = $this->pluginPagesFor(User::factory()->create());

        $groupEntries = $pages->where('key', 'whatsapp');
        $this->assertCount(1, $groupEntries);
        $this->assertEquals('WhatsApp', $groupEntries->first()['title']);
        $this->assertEquals('/whatsapp/numbers', $groupEntries->first()['href']);
        $this->assertNull($groupEntries->first()['group']);

        $this->assertNotNull($pages->firstWhere('key', 'whatsapp_numbers'));
        $this->assertEquals('whatsapp', $pages->firstWhere('key', 'whatsapp_numbers')['group']);
        $this->assertNotNull($pages->firstWhere('key', 'whatsapp_contacts'));
    }

    public function test_group_with_no_members_is_excluded(): void
    {
        RegisterPageGroup::make('empty_group')->title('Empty')->register();

        $pages = $this->pluginPagesFor(User::factory()->create());

        $this->assertNull($pages->firstWhere('key', 'empty_group'));
    }

    public function test_hidden_page_is_excluded_for_a_failing_user_and_included_for_a_passing_one(): void
    {
        RegisterPage::make('gated_page')->title('Gated')
            ->hidden(fn () => ! auth()->user()?->is_admin)
            ->href('/gated')->adminOnly(false)->register();

        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertNull($this->pluginPagesFor($nonAdmin)->firstWhere('key', 'gated_page'));
        $this->assertNotNull($this->pluginPagesFor($admin)->firstWhere('key', 'gated_page'));
    }

    public function test_hiding_a_group_excludes_the_group_and_all_its_members(): void
    {
        RegisterPageGroup::make('whatsapp')->title('WhatsApp')
            ->hidden(fn () => ! auth()->user()?->is_admin)
            ->register();

        RegisterPage::make('whatsapp_numbers')->title('My Numbers')
            ->group('whatsapp')->href('/whatsapp/numbers')->adminOnly(false)->register();

        $pages = $this->pluginPagesFor(User::factory()->create(['is_admin' => false]));

        $this->assertNull($pages->firstWhere('key', 'whatsapp'));
        $this->assertNull($pages->firstWhere('key', 'whatsapp_numbers'));
    }

    public function test_entries_sort_by_order(): void
    {
        RegisterPage::make('page_high')->title('High')->href('/high')
            ->order(100)->adminOnly(false)->register();
        RegisterPage::make('page_low')->title('Low')->href('/low')
            ->order(1)->adminOnly(false)->register();

        $pages = $this->pluginPagesFor(User::factory()->create())
            ->whereIn('key', ['page_high', 'page_low'])
            ->values();

        $this->assertEquals('page_low', $pages[0]['key']);
        $this->assertEquals('page_high', $pages[1]['key']);
    }

    public function test_unregistered_group_reference_throws_when_the_registry_is_resolved(): void
    {
        RegisterPage::make('orphan_page')->title('Orphan')
            ->group('nonexistent')->href('/orphan')->adminOnly(false)->register();

        $this->expectException(\InvalidArgumentException::class);

        app(ResolvePluginPages::class)->handle();
    }
}
