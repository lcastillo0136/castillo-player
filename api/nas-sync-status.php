<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';

header(
    'Content-Type: application/json; charset=utf-8'
);

header(
    'Cache-Control: no-store'
);


const LAST_EPOCH =
    '/var/lib/nas-music-sync/last_success_epoch';

const LAST_HUMAN =
    '/var/lib/nas-music-sync/last_success';

const INTERVAL_SECONDS =
    604800; // 7 días


function respond(
    array $data,
    int $status = 200
): never {
    http_response_code(
        $status
    );

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

if (!CASTILLO_NAS_ENABLED) {
    respond([
        'ok' => true,
        'enabled' => false,
        'has_history' => false,
        'last_success_epoch' => null,
        'last_success' => null,
        'next_sync_epoch' => null,
        'remaining_seconds' => null,
        'due' => false,
        'interval_seconds' =>
            INTERVAL_SECONDS
    ]);
}

if (
    !is_file(
        LAST_EPOCH
    )
) {
    respond([
        'ok' => true,
        'enabled' => true,
        'has_history' => false,
        'last_success_epoch' => null,
        'next_sync_epoch' => null,
        'remaining_seconds' => null,
        'due' => false
    ]);
}


$raw =
    trim(
        (string) @file_get_contents(
            LAST_EPOCH
        )
    );


if (
    $raw === '' ||
    !ctype_digit(
        $raw
    )
) {
    respond([
        'ok' => false,
        'enabled' => true,
        'error' =>
            'El estado de sincronización NAS no es válido.'
    ], 500);
}


$lastEpoch =
    (int) $raw;

$nextEpoch =
    $lastEpoch +
    INTERVAL_SECONDS;

$now =
    time();

$remaining =
    max(
        0,
        $nextEpoch - $now
    );


respond([
    'ok' => true,
    'enabled' => true,

    'has_history' => true,

    'last_success_epoch' =>
        $lastEpoch,

    'last_success' =>
        is_file(
            LAST_HUMAN
        )
            ? trim(
                (string) @file_get_contents(
                    LAST_HUMAN
                )
            )
            : null,

    'next_sync_epoch' =>
        $nextEpoch,

    'remaining_seconds' =>
        $remaining,

    'due' =>
        $remaining === 0,

    'interval_seconds' =>
        INTERVAL_SECONDS
]);