<?php

namespace App\Domain\Deployments\Pipeline;

use App\Domain\Deployments\Events\DeploymentLogged;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Deployments\Support\LiveUpdates;
use App\Support\Ssh\OutputStream;
use Illuminate\Support\Facades\DB;

/**
 * Turns streamed SSH output into masked, line-by-line deployment logs.
 *
 * Output arrives in arbitrary chunks; lines are buffered until complete so a
 * secret split across two chunks is still masked as a whole.
 */
final class DeploymentLogger
{
    /**
     * @var array<string, string> partial line per stream
     */
    private array $buffers = [];

    private string $step = 'pipeline';

    public function __construct(
        private readonly Deployment $deployment,
        private readonly SecretMasker $masker,
    ) {}

    public function forStep(string $step): void
    {
        $this->flush();
        $this->step = $step;
    }

    /**
     * A message from Pushpilot itself (not from the server).
     */
    public function info(string $message): void
    {
        $this->store([[$message, OutputStream::Stdout]]);
    }

    public function write(OutputStream $stream, string $chunk): void
    {
        $text = ($this->buffers[$stream->value] ?? '').str_replace("\r\n", "\n", $chunk);
        $lines = explode("\n", $text);

        $this->buffers[$stream->value] = (string) array_pop($lines);

        $this->store(array_map(fn (string $line): array => [$line, $stream], $lines));
    }

    /**
     * Store incomplete trailing lines (end of a command).
     */
    public function flush(): void
    {
        $pending = [];

        foreach ($this->buffers as $stream => $line) {
            if ($line !== '') {
                $pending[] = [$line, OutputStream::from($stream)];
            }
        }

        $this->buffers = [];
        $this->store($pending);
    }

    /**
     * @param  list<array{0: string, 1: OutputStream}>  $lines
     */
    private function store(array $lines): void
    {
        if ($lines === []) {
            return;
        }

        $now = now();

        $rows = array_map(fn (array $line): array => [
            'deployment_id' => $this->deployment->id,
            'step' => $this->step,
            'line' => mb_substr($this->masker->mask(rtrim($line[0], "\r")), 0, 10000),
            'stream' => $line[1]->value,
            'created_at' => $now,
        ], $lines);

        DB::table('deployment_logs')->insert($rows);

        // Only masked lines ever leave the server (DEP-5, spec 8.3).
        LiveUpdates::send(new DeploymentLogged($this->deployment->id, array_map(
            fn (array $row): array => ['step' => $row['step'], 'line' => $row['line'], 'stream' => $row['stream']],
            $rows,
        )));
    }
}
