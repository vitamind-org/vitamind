<?php

namespace VitaminD\Plugins\Archive\Http\Controllers\Archive;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Patch;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Symfony\Component\Mime\MimeTypes;
use VitaminD\Core\Http\Controllers\Controller;
use VitaminD\Plugins\Archive\Http\Requests\StoreFileRequest;
use VitaminD\Plugins\Archive\Models\File;
use VitaminD\Plugins\Archive\Models\Folder;
use VitaminD\Plugins\Archive\Support\ArchiveScope;

#[Prefix('archive/files')]
#[Middleware(['auth'])]
class FileController extends Controller
{
    // `app`/`public` are deferred — see design.md Non-Goals.
    public const VISIBILITIES = ['user', 'workspace'];

    /**
     * Upload validation order (design.md D7): whitelist extension → max
     * size (both enforced by StoreFileRequest) → detect real MIME from
     * content → cross-check claimed extension against detected MIME →
     * reject on mismatch (assertContentMatchesExtension() below).
     */
    #[Post('/', name: 'archive.files.store')]
    public function store(StoreFileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $folder = isset($data['folder_id']) ? Folder::where('uuid', $data['folder_id'])->firstOrFail() : null;

        if ($folder) {
            $this->authorize('view', $folder);
        }

        /** @var UploadedFile $uploaded */
        $uploaded = $request->file('file');
        $extension = Str::lower($uploaded->getClientOriginalExtension());

        $this->assertContentMatchesExtension($uploaded, $extension);

        $disk = config('archive-plugin.disk');
        $uuid = (string) Str::uuid();
        $path = $uploaded->storeAs($this->shardPath($uuid), "{$uuid}.{$extension}", $disk);

        $file = new File([
            'original_name' => $uploaded->getClientOriginalName(),
            'extension' => $extension,
            'mime_type' => $uploaded->getMimeType(),
            'size' => $uploaded->getSize(),
            'disk' => $disk,
            'path' => $path,
            'folder_id' => $folder?->id,
            // Defaults to the destination folder's visibility as a UI
            // convenience, but stays independently editable thereafter
            // (spec.md's "New item defaults to its destination folder's
            // visibility"). At the root, archive is shared app/workspace
            // storage first, not a personal space — see ArchiveScope.
            'visibility' => $data['visibility'] ?? $folder?->visibility ?? ArchiveScope::defaultVisibility(),
        ]);
        // Same UUID as the physical path above — the model's own
        // `creating` hook only fills this in when it's still unset.
        $file->uuid = $uuid;
        $file->owner_id = user()->id;
        $file->workspace_id = user()->current_workspace_id;
        $file->save();

        return back()->with('success', __('File uploaded.'));
    }

    #[Patch('/{file:uuid}', name: 'archive.files.update')]
    public function update(Request $request, File $file): RedirectResponse
    {
        $this->authorize('update', $file);

        $data = $request->validate([
            'original_name' => ['sometimes', 'string', 'max:255'],
            'visibility' => ['sometimes', Rule::in(self::VISIBILITIES)],
        ]);

        $file->update($data);

        return back()->with('success', __('File updated.'));
    }

    #[Delete('/{file:uuid}', name: 'archive.files.destroy')]
    public function destroy(File $file): RedirectResponse
    {
        $this->authorize('delete', $file);

        // Soft delete only — physical removal is deferred to a
        // forceDelete()-triggered model event (design.md D10).
        $file->delete();

        return back()->with('success', __('File deleted.'));
    }

    protected function shardPath(string $uuid): string
    {
        return substr($uuid, 0, 2).'/'.substr($uuid, 2, 2);
    }

    /**
     * Content-based MIME detection: getMimeType() sniffs the file's actual
     * bytes (via a fileinfo-backed guesser), not the client-supplied
     * extension or Content-Type header — so a text file renamed to
     * `.jpg` is caught here even though it already passed the extension
     * allow-list check.
     */
    protected function assertContentMatchesExtension(UploadedFile $uploaded, string $extension): void
    {
        $detectedMime = $uploaded->getMimeType();
        $expectedExtensions = MimeTypes::getDefault()->getExtensions($detectedMime ?? '');

        if (! in_array($extension, $expectedExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => __('The uploaded file\'s content does not match its extension.'),
            ]);
        }
    }
}
