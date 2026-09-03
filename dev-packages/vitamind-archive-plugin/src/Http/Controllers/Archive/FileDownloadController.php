<?php

namespace VitaminD\Plugins\Archive\Http\Controllers\Archive;

use Illuminate\Support\Facades\Storage;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Prefix;
use Symfony\Component\HttpFoundation\StreamedResponse;
use VitaminD\Core\Http\Controllers\Controller;
use VitaminD\Plugins\Archive\Models\File;

/**
 * The single authorized download/view endpoint for every file, regardless
 * of visibility tier. Behind the `auth` middleware: both tiers that exist
 * in this change (`user`, `workspace`) require a signed-in user, so there
 * is no guest-reachable case to carve out — unlike the deferred `public`
 * tier would need (design.md Non-Goals). Deliberately never uses
 * Storage::url()/temporaryUrl() (design.md D6): this re-runs the policy
 * check on every request instead of trusting a once-generated signature,
 * so changing a file's visibility revokes access to previously shared
 * links immediately.
 */
#[Prefix('archive')]
#[Middleware(['auth'])]
class FileDownloadController extends Controller
{
    #[Get('f/{file:uuid}', name: 'archive.files.download')]
    public function show(File $file): StreamedResponse
    {
        $this->authorize('view', $file);

        return Storage::disk($file->disk)->response($file->path, $file->original_name);
    }
}
