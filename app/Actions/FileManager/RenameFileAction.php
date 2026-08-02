<?php

declare(strict_types=1);

namespace App\Actions\FileManager;

use App\Models\Website;
use App\Services\FileManager\FileManagerService;

class RenameFileAction
{
    public function handle(Website $website, string $path, string $newName): void
    {
        (new FileManagerService($website))->rename($path, $newName);

        activity('file_manager')
            ->causedBy(auth()->user())
            ->performedOn($website)
            ->withProperties(['path' => $path, 'newName' => $newName])
            ->log('renamed file');
    }
}
