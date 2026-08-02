<?php

declare(strict_types=1);

namespace App\Actions\FileManager;

use App\Models\Website;
use App\Services\FileManager\FileManagerService;

class ZipFilesAction
{
    /**
     * @param  list<string>  $paths
     */
    public function handle(Website $website, string $directory, array $paths, string $zipFilename): void
    {
        (new FileManagerService($website))->zip($directory, $paths, $zipFilename);

        activity('file_manager')
            ->causedBy(auth()->user())
            ->performedOn($website)
            ->withProperties(['directory' => $directory, 'paths' => $paths, 'zipFilename' => $zipFilename])
            ->log('created zip archive');
    }
}
