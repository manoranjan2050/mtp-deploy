<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Models\CronJob;
use Illuminate\Support\Collection;

/**
 * Pure string generation - no filesystem or process I/O, so it's fully unit
 * testable without a real crontab binary (which doesn't exist on this
 * Windows dev box). Writing the result to the real system crontab is a
 * separate concern, owned by SystemCrontabService - same split as
 * NginxConfigGeneratorService/WebsiteProvisioningService in Module 3.
 *
 * Every job MTP Deploy manages lives inside one clearly-marked block, so a
 * sync never touches (or clobbers) any cron entry the server's own admin
 * added by hand outside that block.
 */
class CrontabContentBuilder
{
    public const BEGIN_MARKER = '# BEGIN MTP DEPLOY MANAGED CRON JOBS - do not edit this block by hand, changes will be overwritten';

    public const END_MARKER = '# END MTP DEPLOY MANAGED CRON JOBS';

    /**
     * @param  Collection<int, CronJob>  $enabledJobs
     */
    public function build(string $existingCrontab, Collection $enabledJobs): string
    {
        $withoutManagedBlock = rtrim($this->stripManagedBlock($existingCrontab));

        $managedLines = $enabledJobs
            ->map(fn (CronJob $job): string => $job->toCrontabLine())
            ->implode("\n");

        $block = self::BEGIN_MARKER."\n".$managedLines."\n".self::END_MARKER;

        return ($withoutManagedBlock === '' ? '' : $withoutManagedBlock."\n\n").$block."\n";
    }

    private function stripManagedBlock(string $content): string
    {
        $pattern = '/'.preg_quote(self::BEGIN_MARKER, '/').'.*?'.preg_quote(self::END_MARKER, '/').'\s*/s';

        return preg_replace($pattern, '', $content) ?? $content;
    }
}
