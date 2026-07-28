<?php

return [
    'max_consecutive_failures' => env('TRIGGERS_MAX_CONSECUTIVE_FAILURES', 5),
    'default_poll_interval_minutes' => env('TRIGGERS_DEFAULT_POLL_INTERVAL_MINUTES', 5),
    'hook_rate_limit_per_minute' => env('TRIGGERS_HOOK_RATE_LIMIT_PER_MINUTE', 60),
];
