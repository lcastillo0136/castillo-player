<?php

declare(strict_types=1);


require_once __DIR__ . '/core.php';


$file =
    $_GET['file']
    ?? '';


$bars =
    (int) (
        $_GET['bars']
        ?? 220
    );


if (
    !is_string($file) ||
    $file === ''
) {
    jsonResponse([
        'error' =>
            'No se proporcionó archivo.'
    ], 400);
}


/*
 * ============================================================
 * Validate logical MPD path
 * ============================================================
 */

$logicalPrefix =
    CASTILLO_LIBRARY_MPD_PREFIX;


if (
    !str_starts_with(
        $file,
        $logicalPrefix
    )
) {
    jsonResponse([
        'error' =>
            'Archivo fuera de la biblioteca administrada por Castillo.'
    ], 403);
}


$bars =
    max(
        60,
        min(
            400,
            $bars
        )
    );


/*
 * ============================================================
 * Resolve physical library root
 * ============================================================
 */

$allowedRoot =
    realpath(
        CASTILLO_LIBRARY_ROOT
    );


if (
    $allowedRoot === false ||
    !is_dir($allowedRoot)
) {
    jsonResponse([
        'error' =>
            'No se encontró la biblioteca configurada.'
    ], 500);
}


/*
 * ============================================================
 * Logical MPD path -> physical audio path
 * ============================================================
 */

$relativePath =
    substr(
        $file,
        strlen(
            $logicalPrefix
        )
    );


if (
    $relativePath === false ||
    $relativePath === ''
) {
    jsonResponse([
        'error' =>
            'Ruta de archivo inválida.'
    ], 400);
}


$relativePath =
    ltrim(
        str_replace(
            '\\',
            '/',
            $relativePath
        ),
        '/'
    );


$physicalCandidate =
    rtrim(
        $allowedRoot,
        '/'
    ) .
    '/' .
    $relativePath;


$realAudio =
    realpath(
        $physicalCandidate
    );


if (
    $realAudio === false ||
    !is_file($realAudio)
) {
    jsonResponse([
        'error' =>
            'No se encontró el archivo de audio.'
    ], 404);
}


/*
 * ============================================================
 * Security boundary
 * ============================================================
 */

$allowedPrefix =
    rtrim(
        $allowedRoot,
        '/'
    ) .
    '/';


if (
    !str_starts_with(
        $realAudio,
        $allowedPrefix
    )
) {
    jsonResponse([
        'error' =>
            'Ruta de audio no autorizada.'
    ], 403);
}


/*
 * ============================================================
 * Waveform generator
 * ============================================================
 *
 * waveform.py forma parte de Castillo Player.
 *
 * Su ubicación se obtiene del directorio de instalación
 * configurado en castillo.ini.
 */

$waveformScript =
    CASTILLO_WAVEFORM_SCRIPT;


if (
    !is_file(
        $waveformScript
    )
) {
    jsonResponse([
        'error' =>
            'No se encontró el generador de waveform.'
    ], 500);
}


$command = [
    '/usr/bin/python3',

    $waveformScript,

    '--file',
    $realAudio,

    '--bars',
    (string) $bars,

    '--cache-dir',
    CASTILLO_WAVEFORM_DIR
];


$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];


$process =
    proc_open(
        $command,
        $descriptors,
        $pipes,
        null,
        null,
        [
            'bypass_shell' => true
        ]
    );


if (!is_resource($process)) {
    jsonResponse([
        'error' =>
            'No fue posible generar el waveform.'
    ], 500);
}


fclose(
    $pipes[0]
);


$stdout =
    stream_get_contents(
        $pipes[1]
    );

fclose(
    $pipes[1]
);


$stderr =
    stream_get_contents(
        $pipes[2]
    );

fclose(
    $pipes[2]
);


$status =
    proc_close(
        $process
    );


if ($status !== 0) {
    $errorData =
        json_decode(
            trim($stderr),
            true
        );


    jsonResponse([
        'error' =>
            $errorData['error']
            ?? trim($stderr)
            ?: 'No fue posible generar el waveform.'
    ], 500);
}


$result =
    json_decode(
        $stdout,
        true
    );


if (!is_array($result)) {
    jsonResponse([
        'error' =>
            'Respuesta inválida del generador.'
    ], 500);
}


jsonResponse(
    $result
);