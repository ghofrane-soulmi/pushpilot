<?php

namespace App\Enums;

/**
 * v1.0 ships with a single owner account (spec section 6).
 * Member and Viewer roles arrive in v1.1; adding a case here makes PHPStan
 * flag every permission below that must decide what the new role may do.
 */
enum UserRole: string
{
    case Owner = 'owner';

    /**
     * Create, edit and delete servers, and connect to them.
     */
    public function canManageInfrastructure(): bool
    {
        return match ($this) {
            self::Owner => true,
        };
    }
}
