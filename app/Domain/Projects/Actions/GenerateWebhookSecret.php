<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Models\Project;
use Illuminate\Support\Str;

/**
 * DEP-2: create a new webhook secret. The caller shows it once (to paste into
 * GitHub); it is never sent to the browser again (spec 9.1). Any webhook
 * still signed with the old secret is rejected from now on.
 */
final class GenerateWebhookSecret
{
    public function handle(Project $project): string
    {
        $secret = Str::random(48);

        $project->webhook_secret = $secret;
        $project->save();

        return $secret;
    }
}
