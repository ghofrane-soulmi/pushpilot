<?php

namespace App\Support\Ssh;

use App\Support\Ssh\Exceptions\SshAuthenticationException;
use App\Support\Ssh\Exceptions\SshConnectionException;
use App\Support\Ssh\Exceptions\SshException;
use Closure;

/**
 * The only gateway to remote servers. Production uses PhpseclibSshClient;
 * tests swap in FakeSshClient, so no test ever needs a real server.
 *
 * Callers are responsible for escaping: every value interpolated into
 * $command must go through Shell::quote() (POSIX-safe escapeshellarg) or a
 * validated allowlist.
 */
interface SshClient
{
    /**
     * Open a session. Calling connect() again replaces the previous session.
     *
     * @throws SshConnectionException when the host is unreachable or times out
     * @throws SshAuthenticationException when the server rejects the key
     */
    public function connect(SshCredentials $credentials): void;

    /**
     * Run a command and wait for it to finish.
     *
     * Output is streamed to $onOutput as it arrives (chunks, not necessarily
     * whole lines) and is also returned in full in the CommandResult.
     *
     * @param  (Closure(OutputStream, string): void)|null  $onOutput
     *
     * @throws SshException when there is no open session or the command times out
     */
    public function run(string $command, ?Closure $onOutput = null, int $timeoutSeconds = 600): CommandResult;

    public function disconnect(): void;

    public function isConnected(): bool;
}
