<?php

return [

    'deployments' => [

        // Queue that runs DeployJob, with its own Horizon supervisor (spec 16).
        'queue' => env('PUSHPILOT_DEPLOY_QUEUE', 'deployments'),

        // DEP-9: maximum time for one step and for a whole deployment (seconds).
        'step_timeout' => (int) env('PUSHPILOT_STEP_TIMEOUT', 600),
        'global_timeout' => (int) env('PUSHPILOT_DEPLOY_TIMEOUT', 1800),

        // A queued deployment waits this long for the project's lock before giving up.
        'queue_wait' => (int) env('PUSHPILOT_DEPLOY_QUEUE_WAIT', 7200),

    ],

];
