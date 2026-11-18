<?php

namespace App\Domain\Servers\Enums;

/**
 * Reachability badge (SRV-6), updated by every connection test or requirements check.
 */
enum ServerStatus: string
{
    case Unknown = 'unknown';
    case Reachable = 'reachable';
    case Unreachable = 'unreachable';
}
