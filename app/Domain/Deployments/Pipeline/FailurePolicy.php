<?php

namespace App\Domain\Deployments\Pipeline;

/**
 * Spec 8.2, "On failure" column.
 */
enum FailurePolicy
{
    /**
     * Stop and delete the new release; `current` still points at the previous one.
     */
    case AbortAndRemoveRelease;

    /**
     * Same, and warn that the database may already have changed (migrations).
     */
    case AbortWithDatabaseWarning;

    /**
     * The release is already live: record a warning and keep going.
     */
    case Warn;
}
