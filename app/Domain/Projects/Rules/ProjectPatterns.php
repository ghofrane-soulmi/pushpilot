<?php

namespace App\Domain\Projects\Rules;

/**
 * Allowlists for every project value that ends up in a remote shell command.
 * Values are also quoted with Shell::quote(); these checks are the first wall.
 */
final class ProjectPatterns
{
    /**
     * scp-like SSH remotes (git@github.com:owner/repo.git) and ssh:// URLs.
     * HTTPS is not accepted: access goes through the project's deploy key.
     */
    private const SCP_REMOTE = '#^[A-Za-z0-9_][A-Za-z0-9._-]*@[A-Za-z0-9][A-Za-z0-9.-]*:[A-Za-z0-9_][A-Za-z0-9._/-]*$#';

    private const SSH_URL = '#^ssh://[A-Za-z0-9_][A-Za-z0-9._-]*@[A-Za-z0-9][A-Za-z0-9.-]*(:[0-9]{1,5})?/[A-Za-z0-9_][A-Za-z0-9._/-]*$#';

    /**
     * Git ref names (git check-ref-format), restricted to a safe character set.
     */
    private const BRANCH = '#^(?!-)(?!/)(?!.*/$)(?!.*\.\.)(?!.*//)(?!.*\.lock$)(?!.*\.$)(?!.*/\.)[A-Za-z0-9._/-]{1,255}$#';

    private const ABSOLUTE_PATH = '#^(/[A-Za-z0-9_][A-Za-z0-9._-]*)+$#';

    /**
     * Relative path inside the app; a trailing slash marks a directory.
     */
    private const SHARED_PATH = '#^[A-Za-z0-9_.][A-Za-z0-9._-]*(/[A-Za-z0-9_.][A-Za-z0-9._-]*)*/?$#';

    private const PHP_FPM_SERVICE = '#^php[0-9]{1,2}\.[0-9]{1,2}-fpm$#';

    /**
     * Deploying into these would be destructive or nonsensical.
     */
    private const FORBIDDEN_DEPLOY_PATHS = [
        '/bin', '/boot', '/dev', '/etc', '/home', '/lib', '/lib64', '/opt', '/proc',
        '/root', '/run', '/sbin', '/srv', '/sys', '/tmp', '/usr', '/var', '/var/www',
    ];

    public static function isRepositoryUrl(string $value): bool
    {
        return ! str_contains($value, '..')
            && (preg_match(self::SCP_REMOTE, $value) === 1 || preg_match(self::SSH_URL, $value) === 1);
    }

    public static function isBranch(string $value): bool
    {
        return preg_match(self::BRANCH, $value) === 1;
    }

    public static function isDeployPath(string $value): bool
    {
        return preg_match(self::ABSOLUTE_PATH, $value) === 1
            && ! in_array(rtrim($value, '/'), self::FORBIDDEN_DEPLOY_PATHS, true)
            && ! self::hasDotSegment($value);
    }

    public static function isSharedPath(string $value): bool
    {
        return preg_match(self::SHARED_PATH, $value) === 1
            && ! self::hasDotSegment($value)
            && strlen($value) <= 200;
    }

    public static function isPhpFpmService(string $value): bool
    {
        return preg_match(self::PHP_FPM_SERVICE, $value) === 1;
    }

    private static function hasDotSegment(string $path): bool
    {
        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return true;
            }
        }

        return false;
    }
}
