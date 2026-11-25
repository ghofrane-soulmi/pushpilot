<?php

namespace App\Domain\Deployments\Enums;

enum DeploymentTrigger: string
{
    case Manual = 'manual';
    case Webhook = 'webhook';
}
