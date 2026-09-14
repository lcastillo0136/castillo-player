<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';


$root = CASTILLO_LIBRARY_ROOT;


if (
    !is_dir($root)
) {
    jsonResponse(
        [
            'ok' => false,
            'error' =>
                'La biblioteca local no está disponible.'
        ],
        503
    );
}


$total =
    @disk_total_space($root);

$free =
    @disk_free_space($root);


if (
    $total === false ||
    $free === false ||
    $total <= 0
) {
    jsonResponse(
        [
            'ok' => false,
            'error' =>
                'No fue posible leer el almacenamiento local.'
        ],
        503
    );
}


$used =
    max(
        0,
        $total - $free
    );


$usedPercent =
    round(
        (
            $used /
            $total
        ) * 100,
        1
    );


jsonResponse([
    'ok' => true,

    'total_bytes' =>
        (int) $total,

    'used_bytes' =>
        (int) $used,

    'free_bytes' =>
        (int) $free,

    'used_percent' =>
        $usedPercent
]);