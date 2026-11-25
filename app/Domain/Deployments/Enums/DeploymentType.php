<?php

namespace App\Domain\Deployments\Enums;

enum DeploymentType: string
{
    case Deploy = 'deploy';
    case Rollback = 'rollback';
}
