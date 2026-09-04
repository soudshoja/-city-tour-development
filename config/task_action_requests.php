<?php

return [
    // Cross-agent refund/void/reissue acknowledgment workflow.
    // OFF on citycomm (back-office desk model); default ON for iamshoja.
    'enabled' => filter_var(env('TASK_ACTION_REQUESTS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
];
