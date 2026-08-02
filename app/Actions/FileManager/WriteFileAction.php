<?php

declare(strict_types=1);

namespace App\Actions\FileManager;

use App\Models\Website;
use App\Services\FileManager\FileManagerService;

class WriteFileAction
{
    public function handle(Website $website, string $path, string $contents): void
    {
        (new FileManagerService($website))->writeText($path, $contents);

        activity('file_manager')
            ->causedBy(auth()->user())
            ->performedOn($website)
            ->withProperties(['path' => $path, 'bytes' => strlen($contents)])
            ->log('edited file');
    }
}
