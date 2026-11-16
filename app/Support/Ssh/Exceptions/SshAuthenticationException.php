<?php

namespace App\Support\Ssh\Exceptions;

/**
 * The server was reached but rejected the key (SRV-3: "Authentication failed").
 */
class SshAuthenticationException extends SshException {}
