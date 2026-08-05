<?php

return [
    'max_consecutive_failures' => env('TRIGGERS_MAX_CONSECUTIVE_FAILURES', 5),
    'default_poll_interval_minutes' => env('TRIGGERS_DEFAULT_POLL_INTERVAL_MINUTES', 5),
    'hook_rate_limit_per_minute' => env('TRIGGERS_HOOK_RATE_LIMIT_PER_MINUTE', 60),

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    |
    | Trigger events are worked off dedicated queues so that a slow agent run —
    | which blocks for as long as the model takes to answer — cannot stall
    | workflow events or the rest of the application's jobs behind it.
    |
    */

    'queues' => [
        'default' => env('TRIGGERS_QUEUE', 'triggers'),
        'agent' => env('TRIGGERS_AGENT_QUEUE', 'triggers-agent'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing
    |--------------------------------------------------------------------------
    |
    | retry_window_minutes bounds how long a single event may keep retrying
    | before it is failed for good. overlap_release_seconds is how long an event
    | waits before trying again when another event for the same trigger is
    | already in flight.
    |
    */

    'processing' => [
        'retry_window_minutes' => env('TRIGGERS_RETRY_WINDOW_MINUTES', 30),
        'overlap_release_seconds' => env('TRIGGERS_OVERLAP_RELEASE_SECONDS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconciliation
    |--------------------------------------------------------------------------
    |
    | An event is stored before its job is dispatched, so a lost dispatch, a
    | crashed worker, or a flushed queue leaves a row stranded in a non-terminal
    | state. These grace periods decide when the reconciler assumes the job is
    | never coming back and re-queues the event. Both must comfortably exceed
    | normal processing time or the reconciler will race live work.
    |
    */

    'reconcile' => [
        'pending_after_minutes' => env('TRIGGERS_RECONCILE_PENDING_MINUTES', 5),
        'processing_after_minutes' => env('TRIGGERS_RECONCILE_PROCESSING_MINUTES', 15),
        'batch' => env('TRIGGERS_RECONCILE_BATCH', 500),
    ],
];
