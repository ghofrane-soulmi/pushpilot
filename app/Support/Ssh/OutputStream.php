<?php

namespace App\Support\Ssh;

enum OutputStream: string
{
    case Stdout = 'stdout';
    case Stderr = 'stderr';
}
