<?php

namespace App\Domain\Deployments\Support;

use Throwable;

/**
 * Best-effort broadcasting for live logs (DEP-5). The database remains the
 * source of truth: if Reverb is unreachable, the deployment carries on and
 * the page catches up from stored logs. After one failure we stop trying for
 * the rest of the process, so a dead WebSocket server costs one timeout.
 */
final class LiveUpdates
{
    private static bool $unavailable = false;

    public static function send(object $event): void
    {
        if (self::$unavailable) {
            return;
        }

        try {
            broadcast($event);
        } catch (Throwable $e) {
            self::$unavailable = true;
            report($e);
        }
    }

    /**
     * Allow broadcasting again (new worker job, tests).
     */
    public static function reset(): void
    {
        self::$unavailable = false;
    }
}
