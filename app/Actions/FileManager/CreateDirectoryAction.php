<?php

declare(strict_types=1);

namespace App\Actions\FileManager;

use App\Models\Website;
use App\Services\FileManager\FileManagerService;

class CreateDirectoryAction
{
    public function handle(Website $website, string $directory, string $name): void
    {
        (new FileManagerService($website))->createDirectory($directory, $name);

        activity('file_manager')
            ->causedBy(auth()->user())
            ->performedOn($website)
            ->withProperties(['directory' => $directory, 'name' => $name])
            ->log('created directory');
    }
}
