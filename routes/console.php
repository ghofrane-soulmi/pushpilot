<?php

use App\Domain\Deployments\Actions\FailStaleDeployments;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('deployments:fail-stale', function (FailStaleDeployments $failStale) {
    $count = $failStale->handle();
    $this->info($count === 0 ? 'No stale deployments.' : "Marked {$count} stale deployment(s) as failed.");
})->purpose('Mark deployments abandoned by a crashed worker as failed');

Schedule::command('deployments:fail-stale')->everyMinute()->withoutOverlapping();
