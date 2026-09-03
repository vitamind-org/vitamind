<?php

namespace VitaminD\Plugins\Archive\Http\Controllers\Archive;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Patch;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use VitaminD\Core\Http\Controllers\Controller;
use VitaminD\Plugins\Archive\Models\File;
use VitaminD\Plugins\Archive\Models\Folder;
use VitaminD\Plugins\Archive\Support\ArchiveScope;

#[Prefix('archive')]
#[Middleware(['auth'])]
class FolderController extends Controller
{
    // `app`/`public` are deferred — see design.md Non-Goals.
    public const VISIBILITIES = ['user', 'workspace'];

    #[Get('/', name: 'archive.index')]
    public function index(): Response
    {
        return $this->render(null);
    }

    #[Get('/folders/{folder:uuid}', name: 'archive.folders.show')]
    public function show(Folder $folder): Response
    {
        $this->authorize('view', $folder);

        return $this->render($folder);
    }

    #[Post('/folders', name: 'archive.folders.store')]
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'uuid', 'exists:folders,uuid'],
            'visibility' => ['nullable', Rule::in(self::VISIBILITIES)],
        ]);

        $parent = isset($data['parent_id']) ? Folder::where('uuid', $data['parent_id'])->firstOrFail() : null;

        if ($parent) {
            $this->authorize('view', $parent);
        }

        $folder = new Folder([
            'name' => $data['name'],
            'parent_id' => $parent?->id,
            // Defaults to the destination folder's visibility as a UI
            // convenience, but stays independently editable thereafter
            // (spec.md's "New item defaults to its destination folder's
            // visibility"). At the root, archive is shared app/workspace
            // storage first, not a personal space — see ArchiveScope.
            'visibility' => $data['visibility'] ?? $parent?->visibility ?? ArchiveScope::defaultVisibility(),
        ]);
        $folder->owner_id = user()->id;
        $folder->workspace_id = user()->current_workspace_id;
        $folder->save();

        return back()->with('success', __('Folder created.'));
    }

    #[Patch('/folders/{folder:uuid}', name: 'archive.folders.update')]
    public function update(Request $request, Folder $folder): RedirectResponse
    {
        $this->authorize('update', $folder);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'visibility' => ['sometimes', Rule::in(self::VISIBILITIES)],
        ]);

        $folder->update($data);

        return back()->with('success', __('Folder updated.'));
    }

    #[Delete('/folders/{folder:uuid}', name: 'archive.folders.destroy')]
    public function destroy(Folder $folder): RedirectResponse
    {
        $this->authorize('delete', $folder);

        if ($folder->children()->exists() || $folder->files()->exists()) {
            throw ValidationException::withMessages([
                'folder' => __('Only empty folders can be deleted.'),
            ]);
        }

        $folder->delete();

        return back()->with('success', __('Folder deleted.'));
    }

    protected function render(?Folder $folder): Response
    {
        $folders = $this->childFolders($folder)
            ->filter(fn (Folder $child) => Gate::allows('view', $child))
            ->values()
            ->map(fn (Folder $child) => [
                'uuid' => $child->uuid,
                'name' => $child->name,
                'visibility' => $child->visibility,
            ]);

        $files = $this->childFiles($folder)
            ->filter(fn (File $file) => Gate::allows('view', $file))
            ->values()
            ->map(fn (File $file) => [
                'uuid' => $file->uuid,
                'original_name' => $file->original_name,
                'extension' => $file->extension,
                'size' => $file->size,
                'visibility' => $file->visibility,
            ]);

        return Inertia::render('@plugin/archive-plugin/index', [
            'folder' => $folder ? [
                'uuid' => $folder->uuid,
                'name' => $folder->name,
                'visibility' => $folder->visibility,
            ] : null,
            'rootLabel' => ArchiveScope::rootLabel(),
            'defaultVisibility' => $folder?->visibility ?? ArchiveScope::defaultVisibility(),
            'breadcrumb' => $this->breadcrumb($folder),
            'folders' => $folders,
            'files' => $files,
        ]);
    }

    protected function childFolders(?Folder $folder): Collection
    {
        $query = Folder::query()->orderBy('name');

        $query = $folder
            ? $query->where('parent_id', $folder->id)
            : $query->whereNull('parent_id');

        return ArchiveScope::scopeToCurrentWorkspace($query)->get();
    }

    protected function childFiles(?Folder $folder): Collection
    {
        $query = File::query()->orderBy('original_name');

        $query = $folder
            ? $query->where('folder_id', $folder->id)
            : $query->whereNull('folder_id');

        return ArchiveScope::scopeToCurrentWorkspace($query)->get();
    }

    /**
     * @return array<int, array{uuid: string, name: string}>
     */
    protected function breadcrumb(?Folder $folder): array
    {
        $trail = [];

        while ($folder) {
            array_unshift($trail, ['uuid' => $folder->uuid, 'name' => $folder->name]);
            $folder = $folder->parent;
        }

        return $trail;
    }
}
