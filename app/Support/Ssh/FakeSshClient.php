<?php

namespace App\Support\Ssh;

use App\Support\Ssh\Exceptions\SshException;
use Closure;
use PHPUnit\Framework\Assert;

/**
 * In-memory SshClient for tests: records every connection and command and
 * replies with scripted results.
 *
 *     $ssh = FakeSshClient::swap()
 *         ->respondTo('whoami', 'deployer')
 *         ->respondToPattern('/^php -v/', CommandResult::failure(127, 'php: not found'));
 *
 *     // ... exercise the code under test ...
 *
 *     $ssh->assertConnectedTo('203.0.113.10')->assertRan('whoami');
 *
 * Unscripted commands succeed with empty output. When several responses match,
 * the most recently registered one wins.
 */
final class FakeSshClient implements SshClient
{
    /** @var list<SshCredentials> */
    private array $connections = [];

    /** @var list<string> */
    private array $commands = [];

    /** @var list<array{matcher: Closure(string): bool, result: CommandResult}> */
    private array $responses = [];

    private ?SshException $connectFailure = null;

    private bool $connected = false;

    /**
     * Bind a new fake into the container in place of the real client.
     */
    public static function swap(): self
    {
        $fake = new self;

        app()->instance(SshClient::class, $fake);

        return $fake;
    }

    public function respondTo(string $command, CommandResult|string $result): self
    {
        return $this->addResponse(fn (string $ran): bool => $ran === $command, $result);
    }

    public function respondToPattern(string $regex, CommandResult|string $result): self
    {
        return $this->addResponse(fn (string $ran): bool => preg_match($regex, $ran) === 1, $result);
    }

    public function failConnectionWith(SshException $exception): self
    {
        $this->connectFailure = $exception;

        return $this;
    }

    public function connect(SshCredentials $credentials): void
    {
        $this->connections[] = $credentials;

        if ($this->connectFailure !== null) {
            $this->connected = false;

            throw $this->connectFailure;
        }

        $this->connected = true;
    }

    public function run(string $command, ?Closure $onOutput = null, int $timeoutSeconds = 600): CommandResult
    {
        if (! $this->connected) {
            throw new SshException('Cannot run a command without an open SSH session.');
        }

        $this->commands[] = $command;

        $result = $this->resultFor($command);

        if ($onOutput !== null) {
            if ($result->output !== '') {
                $onOutput(OutputStream::Stdout, $result->output);
            }

            if ($result->errorOutput !== '') {
                $onOutput(OutputStream::Stderr, $result->errorOutput);
            }
        }

        return $result;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * @return list<string>
     */
    public function commands(): array
    {
        return $this->commands;
    }

    /**
     * @return list<SshCredentials>
     */
    public function connections(): array
    {
        return $this->connections;
    }

    public function assertConnectedTo(string $host, ?string $username = null): self
    {
        $matches = array_filter(
            $this->connections,
            fn (SshCredentials $c): bool => $c->host === $host && ($username === null || $c->username === $username),
        );

        Assert::assertNotEmpty($matches, "No SSH connection was opened to [{$host}].");

        return $this;
    }

    public function assertRan(string $command): self
    {
        Assert::assertContains($command, $this->commands, "Command [{$command}] was not run. Ran:\n".$this->listCommands());

        return $this;
    }

    public function assertRanMatching(string $regex): self
    {
        $matches = preg_grep($regex, $this->commands);

        Assert::assertNotEmpty($matches, "No command matched [{$regex}]. Ran:\n".$this->listCommands());

        return $this;
    }

    public function assertNotRan(string $command): self
    {
        Assert::assertNotContains($command, $this->commands, "Command [{$command}] was run unexpectedly.");

        return $this;
    }

    /**
     * @param  list<string>  $commands
     */
    public function assertRanInOrder(array $commands): self
    {
        $ran = array_values(array_filter($this->commands, fn (string $c): bool => in_array($c, $commands, true)));

        Assert::assertSame($commands, $ran, "Commands were not run in the expected order. Ran:\n".$this->listCommands());

        return $this;
    }

    public function assertNothingRan(): self
    {
        Assert::assertSame([], $this->commands, "Expected no commands. Ran:\n".$this->listCommands());

        return $this;
    }

    /**
     * @param  Closure(string): bool  $matcher
     */
    private function addResponse(Closure $matcher, CommandResult|string $result): self
    {
        $this->responses[] = [
            'matcher' => $matcher,
            'result' => is_string($result) ? CommandResult::success($result) : $result,
        ];

        return $this;
    }

    private function resultFor(string $command): CommandResult
    {
        foreach (array_reverse($this->responses) as $response) {
            if (($response['matcher'])($command)) {
                return $response['result'];
            }
        }

        return CommandResult::success();
    }

    private function listCommands(): string
    {
        return $this->commands === [] ? '  (none)' : '  '.implode("\n  ", $this->commands);
    }
}
