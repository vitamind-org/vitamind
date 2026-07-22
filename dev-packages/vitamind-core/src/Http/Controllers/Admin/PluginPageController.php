<?php

namespace VitaminD\Core\Http\Controllers\Admin;

use VitaminD\Core\Http\Controllers\Controller;
use VitaminD\PluginSdk\RegisterPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Patch;
use Spatie\RouteAttributes\Attributes\Post;

class PluginPageController extends Controller
{
    #[Get('p/{pageKey}', name: 'plugins.page')]
    #[Middleware(['auth', 'verified'])]
    public function renderPage(string $pageKey, Request $request): Response
    {
        $page = RegisterPage::find($pageKey);
        if (! $page) {
            abort(404, 'Plugin page not found');
        }

        // Security check: if page is admin-only, user must be admin
        if ($page->isAdminOnly() && ! $request->user()?->isAdmin()) {
            abort(404);
        }

        $tabs = $page->getTabs();
        $activeTabKey = $request->input('tab', array_key_first($tabs));
        $activeTabTable = $tabs[$activeTabKey] ?? null;

        $props = [
            'page' => $page->toArray(),
            'activeTab' => $activeTabKey,
            'tableData' => null,
            'options' => [],
        ];

        if ($activeTabTable) {
            $modelClass = $activeTabTable->getModel();
            $query = $modelClass::query();

            if (! empty($activeTabTable->getRelations())) {
                $query->with($activeTabTable->getRelations());
            }

            // Apply registered filters dynamically
            foreach ($activeTabTable->getFilters() as $filter) {
                $val = $request->input($filter->getKey());
                if ($val !== null && $val !== '' && $val !== '_all') {
                    $query->where($filter->getKey(), $val);
                }
            }

            // Simple Search
            if ($search = $request->input('search')) {
                $searchableColumns = [];
                foreach ($activeTabTable->getColumns() as $col) {
                    if ($col['searchable'] ?? false) {
                        $searchableColumns[] = $col['key'];
                    }
                }

                if (! empty($searchableColumns)) {
                    $query->where(function ($q) use ($search, $searchableColumns) {
                        foreach ($searchableColumns as $i => $col) {
                            if ($i === 0) {
                                $q->where($col, 'like', "%{$search}%");
                            } else {
                                $q->orWhere($col, 'like', "%{$search}%");
                            }
                        }
                    });
                }
            }

            // Simple Sort
            if ($sortBy = $request->input('sort_by')) {
                $sortDir = $request->input('sort_dir', 'asc');
                $query->orderBy($sortBy, $sortDir);
            } else {
                $query->orderBy('id', 'desc');
            }

            $paginated = $query->simplePaginate(config('vitamin-d.pagination_size', 10))
                ->withQueryString();

            $paginatedArray = $paginated->toArray();

            $props['tableData'] = [
                'data' => $paginatedArray['data'],
                'links' => [
                    'first' => $paginatedArray['first_page_url'] ?? null,
                    'prev' => $paginatedArray['prev_page_url'] ?? null,
                    'next' => $paginatedArray['next_page_url'] ?? null,
                    'last' => $paginatedArray['last_page_url'] ?? null,
                ],
                'meta' => [
                    'current_page' => $paginatedArray['current_page'],
                    'from' => $paginatedArray['from'] ?? null,
                    'to' => $paginatedArray['to'] ?? null,
                    'per_page' => $paginatedArray['per_page'],
                    'path' => $paginatedArray['path'],
                ],
            ];

            $props['options'] = $activeTabTable->getOptions();
        }

        return Inertia::render('plugins/dynamic-page', $props);
    }

    #[Post('p/{pageKey}/{tabKey}', name: 'plugins.page.store')]
    #[Middleware(['auth', 'verified'])]
    public function store(string $pageKey, string $tabKey, Request $request): RedirectResponse
    {
        $page = RegisterPage::find($pageKey);
        if (! $page || ($page->isAdminOnly() && ! $request->user()?->isAdmin())) {
            abort(403);
        }

        $table = $page->getTabs()[$tabKey] ?? null;
        if (! $table) {
            abort(404);
        }

        $form = $table->getForm();
        if ($form) {
            $rules = $form->getFieldValidationRules();
        } else {
            $rules = $this->getRules($pageKey, $tabKey);
        }
        $data = $request->validate($rules);

        $modelClass = $table->getModel();
        $modelClass::create($data);

        return back()->with('success', 'Created successfully.');
    }

    #[Patch('p/{pageKey}/{tabKey}/{id}', name: 'plugins.page.update')]
    #[Middleware(['auth', 'verified'])]
    public function update(string $pageKey, string $tabKey, $id, Request $request): RedirectResponse
    {
        $page = RegisterPage::find($pageKey);
        if (! $page || ($page->isAdminOnly() && ! $request->user()?->isAdmin())) {
            abort(403);
        }

        $table = $page->getTabs()[$tabKey] ?? null;
        if (! $table) {
            abort(404);
        }

        $modelClass = $table->getModel();
        $record = $modelClass::findOrFail($id);

        $form = $table->getForm();
        if ($form) {
            $rules = $form->getFieldValidationRules();
            // Replace {id} placeholder with actual record id
            foreach ($rules as $fieldName => $fieldRules) {
                if (is_array($fieldRules)) {
                    $rules[$fieldName] = array_map(fn($r) => is_string($r) ? str_replace('{id}', $id, $r) : $r, $fieldRules);
                } elseif (is_string($fieldRules)) {
                    $rules[$fieldName] = str_replace('{id}', $id, $fieldRules);
                }
            }
        } else {
            $rules = $this->getRules($pageKey, $tabKey, $id);
        }
        $data = $request->validate($rules);

        $record->update($data);

        return back()->with('success', 'Updated successfully.');
    }

    #[Delete('p/{pageKey}/{tabKey}/{id}', name: 'plugins.page.destroy')]
    #[Middleware(['auth', 'verified'])]
    public function destroy(string $pageKey, string $tabKey, $id): RedirectResponse
    {
        $page = RegisterPage::find($pageKey);
        if (! $page || ($page->isAdminOnly() && ! $request->user()?->isAdmin())) {
            abort(403);
        }

        $table = $page->getTabs()[$tabKey] ?? null;
        if (! $table) {
            abort(404);
        }

        $modelClass = $table->getModel();
        $record = $modelClass::findOrFail($id);
        $record->delete();

        return back()->with('success', 'Deleted successfully.');
    }

    private function getRules(string $pageKey, string $tabKey, $id = null): array
    {
        return [];
    }
}
