<?php

declare(strict_types=1);

namespace App\Services\Terminal;

/**
 * The Terminal module is, by design, arbitrary shell access for admins - the
 * same trust boundary as a real SSH session. This guard is not a security
 * boundary in the sense `WhitelistedOperation` is for `SystemCommandService`
 * (an allowlist of exactly what may run) - it's a confirm-before-you-nuke-it
 * safety net against a fixed, auditable set of unmistakably destructive
 * patterns, matching the "root-command protection (confirm-to-run guard for
 * destructive patterns)" feature in docs/Features.md.
 */
class DangerousCommandGuard
{
    /**
     * @var list<string>
     */
    private const DANGEROUS_PATTERNS = [
        '/\brm\s+(-\w*r\w*f\w*|-\w*f\w*r\w*)\s+(\/|~|\*|\$HOME)/i',
        '/\bmkfs(\.\w+)?\b/i',
        '/\bdd\s+.*\bof=\/dev\//i',
        '/:\(\)\s*\{\s*:\s*\|\s*:\s*&\s*\}\s*;\s*:/', // fork bomb
        '/\bDROP\s+DATABASE\b/i',
        '/\bDROP\s+TABLE\b/i',
        '/\bTRUNCATE\s+TABLE\b/i',
        '/\bshutdown\b/i',
        '/\breboot\b/i',
        '/\bdel\s+\/[fFsSqQ]+\s+.*[a-zA-Z]:\\\\/i',
        '/\bformat\s+[a-zA-Z]:/i',
        '/\brmdir\s+\/[sS]\b/i',
        '/>\s*\/dev\/sd[a-z]/i',
        '/\bchmod\s+(-R\s+)?000\b/i',
        '/\bchown\s+(-R\s+)?.*\s+\/\s*$/i',
    ];

    public function isDangerous(string $command): bool
    {
        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $command) === 1) {
                return true;
            }
        }

        return false;
    }
}
