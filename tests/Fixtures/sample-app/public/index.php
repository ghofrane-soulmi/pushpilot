<?php

// Shows which release is serving, and that the shared .env is linked in.
$env = @parse_ini_file(__DIR__.'/../.env') ?: [];

echo 'release='.basename(dirname(__DIR__)).PHP_EOL;
echo 'app_name='.($env['APP_NAME'] ?? '').PHP_EOL;
