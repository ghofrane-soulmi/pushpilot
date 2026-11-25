<?php

namespace App\Domain\Deployments\Pipeline;

use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\EnvFile;

/**
 * Replaces secret values in deployment output with a mask (spec 8.3, 9.1):
 * every .env value, the deploy private key and the webhook secret.
 */
final class SecretMasker
{
    public const MASK = '••••••••';

    /**
     * Shorter values ("1", "true", "local") are too common to mask without
     * destroying the log, and are not meaningful secrets.
     */
    private const MIN_LENGTH = 6;

    /**
     * @var list<string> longest first, so overlapping secrets mask fully
     */
    private array $secrets;

    /**
     * @param  list<string>  $secrets
     */
    public function __construct(array $secrets)
    {
        $secrets = array_values(array_unique(array_filter(
            array_map('trim', $secrets),
            fn (string $secret): bool => mb_strlen($secret) >= self::MIN_LENGTH,
        )));

        usort($secrets, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $this->secrets = $secrets;
    }

    public static function forProject(Project $project): self
    {
        $keyLines = preg_split('/\R/', $project->deploy_private_key) ?: [];

        return new self([
            ...EnvFile::parse($project->env_content)->values(),
            ...array_values(array_filter($keyLines, fn (string $line): bool => ! str_starts_with($line, '-----'))),
            $project->webhook_secret,
        ]);
    }

    public function mask(string $text): string
    {
        return $this->secrets === [] ? $text : str_replace($this->secrets, self::MASK, $text);
    }
}
