<?php

namespace App\Domain\Projects\Enums;

/**
 * When a custom hook runs in the deployment pipeline (PRJ-6).
 */
enum HookStage: string
{
    case BeforeBuild = 'before_build';
    case AfterBuild = 'after_build';
    case BeforeActivate = 'before_activate';
    case AfterActivate = 'after_activate';

    public function label(): string
    {
        return match ($this) {
            self::BeforeBuild => 'Before build',
            self::AfterBuild => 'After build',
            self::BeforeActivate => 'Before activate',
            self::AfterActivate => 'After activate',
        };
    }
}
