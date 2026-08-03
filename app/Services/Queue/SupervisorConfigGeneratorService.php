<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\Models\QueueWorker;

/**
 * Pure string generation - no filesystem or process I/O, so it's fully unit
 * testable without a real Supervisor install. Writing the file and calling
 * `supervisorctl` are separate concerns, owned by SupervisorProcessService -
 * same split as NginxConfigGeneratorService/WebsiteProvisioningService
 * (Module 3).
 */
class SupervisorConfigGeneratorService
{
    public function generate(QueueWorker $worker): string
    {
        $website = $worker->website;
        $logPath = rtrim($website->document_root, '/\\').'/storage/logs/worker.log';

        return <<<CONF
        ; Managed by MTP Deploy - do not edit outside the panel; changes will
        ; be overwritten on the next save.
        [program:{$worker->supervisor_program_name}]
        process_name=%(program_name)s_%(process_num)02d
        command=php artisan queue:work {$worker->connection} --queue={$worker->queue} --sleep=3 --tries=3 --max-time=3600
        directory={$website->document_root}
        autostart=true
        autorestart=true
        stopasgroup=true
        killasgroup=true
        user=www-data
        numprocs={$worker->processes}
        redirect_stderr=true
        stdout_logfile={$logPath}
        stopwaitsecs=3600

        CONF;
    }
}
