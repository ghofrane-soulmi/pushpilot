<?php

namespace App\Support\Ssh\Exceptions;

/**
 * A command did not finish within its timeout. The session is closed.
 */
class SshTimeoutException extends SshException {}
