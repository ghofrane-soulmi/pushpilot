<?php

use App\Domain\Deployments\Models\Deployment;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

// DEP-5: live deployment logs, for users allowed to view the deployment.
Broadcast::channel('deployments.{deployment}', function (User $user, Deployment $deployment) {
    return $user->can('view', $deployment);
});
