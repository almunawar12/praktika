<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attachment;
use App\Models\Journal;
use App\Models\Monitoring;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * One upload path for every kind of documentation (ADD § ADR-06).
 *
 * The parameter names the concrete attachables rather than an interface: Eloquent's
 * relation generics do not survive an interface boundary, and a plain union is both
 * type-safe and honest.
 */
class AttachmentService
{
    public function store(
        Journal|Monitoring $attachable,
        UploadedFile $file,
        User $uploader,
        ?string $caption = null,
    ): Attachment {
        $disk = $this->disk();

        $path = $file->store($this->folderFor($attachable), $disk);

        // `store()` returns false when the disk write fails. Recording a row that
        // points at a file which does not exist would be worse than failing here.
        if ($path === false) {
            throw new RuntimeException('Gagal menyimpan lampiran.');
        }

        return $attachable->attachments()->create([
            'uploaded_by' => $uploader->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'caption' => $caption,
        ]);
    }

    public function delete(Attachment $attachment): void
    {
        // File first, row second: an orphaned row points at nothing and breaks the
        // page, while an orphaned file is merely wasted bytes.
        Storage::disk($attachment->disk)->delete($attachment->path);

        $attachment->delete();
    }

    private function folderFor(Journal|Monitoring $attachable): string
    {
        return sprintf(
            'attachments/%s/%s',
            str_replace('\\', '-', strtolower(class_basename($attachable))),
            $attachable->getKey(),
        );
    }

    private function disk(): string
    {
        return (string) config('filesystems.default');
    }
}
