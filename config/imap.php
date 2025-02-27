<?php
return [
    'default' => env('IMAP_DEFAULT_ACCOUNT', 'office365'),
    'accounts' => [
        'default' => [
            'host'          => env('IMAP_HOST', 'imap.gmail.com'),
            'port'          => env('IMAP_PORT', 993),
            'encryption'    => env('IMAP_ENCRYPTION', 'ssl'),
            'validate_cert' => env('IMAP_VALIDATE_CERT', false),
            'username'      => env('IMAP_USERNAME'),
            'password'      => env('IMAP_PASSWORD'),
            'protocol'      => env('IMAP_PROTOCOL', 'imap'),
        ],
    ],
];
