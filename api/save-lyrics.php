<?php

declare(strict_types=1);


require_once __DIR__ . '/core.php';


/*
 * ============================================================
 * Optional NAS / pending integration
 * ============================================================
 *
 * Guardar una letra pertenece al Core.
 *
 * Registrar el cambio para sincronizarlo posteriormente
 * al NAS es una característica opcional.
 */

if (CASTILLO_NAS_ENABLED) {
    $pendingLibrary =
        __DIR__ .
        '/pending-lib.php';


    if (is_file($pendingLibrary)) {
        require_once $pendingLibrary;

    } else {
        error_log(
            '[Castillo pending] ' .
            'NAS habilitado pero pending-lib.php no existe.'
        );
    }
}


/*
 * ============================================================
 * Request
 * ============================================================
 */

$raw =
    file_get_contents(
        'php://input'
    );


$data =
    json_decode(
        $raw ?: '{}',
        true
    );


if (!is_array($data)) {
    jsonResponse([
        'error' =>
            'JSON inválido.'
    ], 400);
}


$file =
    trim(
        (string) (
            $data['file']
            ?? ''
        )
    );


$content =
    $data['content']
    ?? null;


$expectedMtime =
    (int) (
        $data['expected_mtime']
        ?? -1
    );


$expectedSize =
    (int) (
        $data['expected_size']
        ?? -1
    );


if (
    $file === '' ||
    !is_string($content)
) {
    jsonResponse([
        'error' =>
            'Faltan datos para guardar la letra.'
    ], 400);
}


/*
 * Máximo 2 MiB por archivo LRC.
 */

if (
    strlen($content) >
    2 * 1024 * 1024
) {
    jsonResponse([
        'error' =>
            'El contenido es demasiado grande.'
    ], 413);
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
 * Logical MPD path -> physical path
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
 *
 * realpath() resuelve enlaces simbólicos y ../
 * antes de verificar que el archivo siga dentro
 * de la biblioteca autorizada.
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
            'Ruta no autorizada.'
    ], 403);
}


/*
 * ============================================================
 * LRC helper
 * ============================================================
 */

if (
    !is_file(
        CASTILLO_LRC_HELPER
    ) ||
    !is_executable(
        CASTILLO_LRC_HELPER
    )
) {
    jsonResponse([
        'error' =>
            'El helper de guardado LRC no está disponible.'
    ], 500);
}


$command = [
    '/usr/bin/sudo',
    '-n',

    CASTILLO_LRC_HELPER,

    '--audio',
    $realAudio,

    '--expected-mtime',
    (string) $expectedMtime,

    '--expected-size',
    (string) $expectedSize
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
            'No fue posible iniciar el guardado.'
    ], 500);
}


/*
 * El contenido LRC se envía por stdin.
 *
 * No se incorpora a una línea de shell.
 */

fwrite(
    $pipes[0],
    $content
);

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


/*
 * El helper devuelve 23 cuando detecta
 * conflicto de modificación.
 */

if ($status !== 0) {
    $error =
        json_decode(
            trim($stderr),
            true
        );


    $httpStatus =
        $status === 23
            ? 409
            : 500;


    jsonResponse([
        'error' =>
            $error['error']
            ?? trim($stderr)
            ?: 'No fue posible guardar la letra.'
    ], $httpStatus);
}


$result =
    json_decode(
        trim($stdout),
        true
    );


if (!is_array($result)) {
    jsonResponse([
        'error' =>
            'Respuesta inválida del guardado.'
    ], 500);
}


/*
 * ============================================================
 * Physical LRC path
 * ============================================================
 */

$lrcPath =
    dirname(
        $realAudio
    ) .
    DIRECTORY_SEPARATOR .
    pathinfo(
        $realAudio,
        PATHINFO_FILENAME
    ) .
    '.lrc';


/*
 * ============================================================
 * Optional pending NAS registration
 * ============================================================
 *
 * El guardado local ya terminó correctamente.
 *
 * Un problema al registrar el cambio NAS nunca debe
 * convertir un guardado LRC válido en un error.
 */

if (
    CASTILLO_NAS_ENABLED &&
    function_exists(
        'registerPendingChange'
    )
) {
    try {
        registerPendingChange(
            $lrcPath,
            'lyrics',
            $file
        );

    } catch (Throwable $error) {
        error_log(
            '[Castillo pending] Lyrics: ' .
            $error->getMessage()
        );
    }
}


jsonResponse(
    $result
);