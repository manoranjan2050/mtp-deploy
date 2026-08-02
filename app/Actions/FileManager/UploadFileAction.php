<?php

declare(strict_types=1);

namespace App\Actions\FileManager;

use App\Models\Website;
use App\Services\FileManager\FileManagerService;
use Illuminate\Http\UploadedFile;

class UploadFileAction
{
    public function handle(Website $website, string $directory, UploadedFile $file): void
    {
        $originalName = $file->getClientOriginalName();

        (new FileManagerService($website))->upload($directory, $file);

        activity('file_manager')
            ->causedBy(auth()->user())
            ->performedOn($website)
            ->withProperties(['directory' => $directory, 'name' => $originalName])
            ->log('uploaded file');
    }
}
