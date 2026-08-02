<?php

declare(strict_types=1);

namespace App\Actions\FileManager;

use App\Models\Website;
use App\Services\FileManager\FileManagerService;

class UnzipFileAction
{
    public function handle(Website $website, string $path): void
    {
        (new FileManagerService($website))->unzip($path);

        activity('file_manager')
            ->causedBy(auth()->user())
            ->performedOn($website)
            ->withProperties(['path' => $path])
            ->log('extracted zip archive');
    }
}
