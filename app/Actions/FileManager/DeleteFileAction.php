<?php

declare(strict_types=1);

namespace App\Actions\FileManager;

use App\Models\Website;
use App\Services\FileManager\FileManagerService;

class DeleteFileAction
{
    public function handle(Website $website, string $path): void
    {
        (new FileManagerService($website))->delete($path);

        activity('file_manager')
            ->causedBy(auth()->user())
            ->performedOn($website)
            ->withProperties(['path' => $path])
            ->log('deleted file');
    }
}
