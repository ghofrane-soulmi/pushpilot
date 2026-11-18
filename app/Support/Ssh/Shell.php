<?php

namespace App\Support\Ssh;

/**
 * Quoting for commands that run on remote Linux servers.
 *
 * PHP's escapeshellarg() follows the platform Pushpilot itself runs on: on
 * Windows it produces cmd.exe quoting, which is wrong (and unsafe) for the
 * POSIX shell on the server. Every value placed in a remote command must go
 * through Shell::quote() instead.
 */
final class Shell
{
    /**
     * Single-quote a value for a POSIX shell: nothing inside is interpreted.
     */
    public static function quote(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }
}
