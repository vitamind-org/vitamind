<?php

namespace VitaminD\Core\Actions\Plugins;

use VitaminD\PluginSdk\RegisterPage;
use VitaminD\PluginSdk\RegisterPageGroup;

/**
 * Builds the `pluginPages` Inertia prop for the current request: every
 * registered `RegisterPage`/`RegisterPageGroup` entry, minus anything a
 * `hidden()` closure excludes for this request (cascading from a hidden
 * group to its member pages), plus one collapsed entry per visible group —
 * its `href` pointing at that group's first-registered (and still visible)
 * member — sorted by `order`.
 *
 * A group entry always resolves `group: null, placement: 'main'` itself
 * (groups only ever render in the main sidebar — see
 * `docs/plugin-development/menu-registration.md`), so it needs no
 * discriminator field: the main sidebar simply renders every `pluginPages`
 * entry with `group === null`, whether it's a flat page or a group's own
 * landing entry.
 *
 * A group's landing `href` is its lowest-`order` visible member (ties
 * broken by registration order) — not simply the first one registered.
 * Registration order alone isn't a reliable "primary member" signal once
 * both plugins (which typically register directly in their own `boot()`)
 * and core's native groups (registered from a `booted()` callback, which
 * always runs after every provider's `boot()`) can join the same group; a
 * native group's intended primary member (e.g. Profile for `settings`)
 * registers with an explicitly low `order()` for exactly this reason.
 */
final readonly class ResolvePluginPages
{
    public function handle(): array
    {
        $hiddenGroupKeys = collect(RegisterPageGroup::get())
            ->filter(fn (RegisterPageGroup $group) => $group->isHidden())
            ->keys();

        $pages = collect(RegisterPage::get())
            ->reject(fn (RegisterPage $page) => $page->isHidden())
            ->map(fn (RegisterPage $page) => $page->toArray())
            ->reject(fn (array $page) => $page['group'] !== null && $hiddenGroupKeys->contains($page['group']))
            ->values();

        $groupEntries = collect(RegisterPageGroup::get())
            ->reject(fn (RegisterPageGroup $group) => $group->isHidden())
            ->map(function (RegisterPageGroup $group) use ($pages) {
                $landing = $pages->where('group', $group->getKey())->sortBy('order')->first();

                if ($landing === null) {
                    return null;
                }

                return [
                    'key' => $group->getKey(),
                    'title' => $group->getTitle(),
                    'icon' => $group->getIcon(),
                    'admin_only' => false,
                    'tabs' => [],
                    'description' => null,
                    'placement' => 'main',
                    'href' => $landing['href'],
                    'group' => null,
                    'order' => $group->getOrder(),
                    'external' => false,
                ];
            })
            ->filter()
            ->values();

        return $pages->concat($groupEntries)
            ->sortBy('order')
            ->values()
            ->all();
    }
}
