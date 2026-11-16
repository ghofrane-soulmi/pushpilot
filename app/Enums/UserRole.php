<?php

namespace App\Enums;

/**
 * v1.0 ships with a single owner account (spec section 6).
 * Member and Viewer roles arrive in v1.1.
 */
enum UserRole: string
{
    case Owner = 'owner';
}
