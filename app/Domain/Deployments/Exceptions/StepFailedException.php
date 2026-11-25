<?php

namespace App\Domain\Deployments\Exceptions;

use RuntimeException;

/**
 * A pipeline step failed; the message is safe to show to the user.
 */
final class StepFailedException extends RuntimeException {}
