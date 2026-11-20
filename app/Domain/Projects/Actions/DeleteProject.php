<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Models\Project;

/**
 * Removes the project from Pushpilot. Files on the server are left untouched.
 */
final class DeleteProject
{
    public function handle(Project $project): void
    {
        $project->delete();
    }
}
