<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves attendance photos from the private disk.
 *
 * These are photographs of minors at their workplace. They are never on a public
 * disk, and every read is authorized against the attendance they belong to — a
 * guessable URL must not be enough to see one.
 */
class AttendancePhotoController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $path = (string) $request->query('path');

        $attendance = Attendance::query()
            ->where('check_in_photo_path', $path)
            ->orWhere('check_out_photo_path', $path)
            ->firstOrFail();

        $this->authorize('view', $attendance);

        $disk = Storage::disk((string) config('filesystems.default'));

        abort_unless($disk->exists($path), 404);

        return $disk->response($path);
    }
}
