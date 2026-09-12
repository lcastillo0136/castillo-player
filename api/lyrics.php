<?php

declare(strict_types=1);


require_once __DIR__ . '/core.php';


header(
    'Content-Type: application/json; charset=utf-8'
);

header(
    'Cache-Control: no-store'
);


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
        JSON_UNESCAPED_SLASHES |
        JSON_PRETTY_PRINT
    );

    exit;
}


/*
 * ============================================================
 * Input
 * ============================================================
 */

$file =
    $_GET['file']
    ?? '';


if (
    !is_string($file) ||
    $file === ''
) {
    respond([
        'error' =>
            'No se proporcionó archivo.'
    ], 400);
}


/*
 * ============================================================
 * Validate logical MPD path
 * ============================================================
 *
 * Castillo solo permite acceder a archivos que pertenezcan
 * a la biblioteca administrada configurada en:
 *
 *   library_mpd_prefix
 *
 * Ejemplo:
 *
 *   USB/MY_DRIVE/Music/
 */

$allowedLogicalPrefix =
    CASTILLO_LIBRARY_MPD_PREFIX;


if (
    !str_starts_with(
        $file,
        $allowedLogicalPrefix
    )
) {
    respond([
        'error' =>
            'La canción no pertenece a la biblioteca administrada por Castillo.'
    ], 403);
}


/*
 * ============================================================
 * Resolve physical library root
 * ============================================================
 */

$allowedRealRoot =
    realpath(
        CASTILLO_LIBRARY_ROOT
    );


if (
    $allowedRealRoot === false ||
    !is_dir($allowedRealRoot)
) {
    respond([
        'error' =>
            'No se encontró la biblioteca configurada.'
    ], 500);
}


/*
 * ============================================================
 * Logical MPD path -> physical path
 * ============================================================
 *
 * Ejemplo:
 *
 * MPD:
 *   USB/MY_DRIVE/Music/Artist/Album/song.mp3
 *
 * Config:
 *   library_mpd_prefix =
 *       USB/MY_DRIVE/Music/
 *
 * Física:
 *   <library_root>/Artist/Album/song.mp3
 */

$relativePath =
    substr(
        $file,
        strlen(
            $allowedLogicalPrefix
        )
    );


if (
    $relativePath === false ||
    $relativePath === ''
) {
    respond([
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
        $allowedRealRoot,
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
    respond([
        'error' =>
            'No se encontró el archivo de audio.'
    ], 404);
}


/*
 * ============================================================
 * Security boundary
 * ============================================================
 *
 * realpath() resuelve:
 *
 *   ../../
 *   enlaces simbólicos
 *   componentes relativos
 *
 * y después comprobamos que el archivo final continúe
 * dentro de la biblioteca configurada.
 */

$allowedPrefix =
    rtrim(
        $allowedRealRoot,
        '/'
    ) .
    '/';


if (
    !str_starts_with(
        $realAudio,
        $allowedPrefix
    )
) {
    respond([
        'error' =>
            'Ruta fuera de la biblioteca autorizada.'
    ], 403);
}


/*
 * ============================================================
 * LRC path
 * ============================================================
 */

$extension =
    pathinfo(
        $realAudio,
        PATHINFO_EXTENSION
    );


if ($extension === '') {
    respond([
        'error' =>
            'El archivo de audio no tiene extensión.'
    ], 400);
}


$lrcPath =
    substr(
        $realAudio,
        0,
        -(
            strlen(
                $extension
            ) + 1
        )
    ) .
    '.lrc';


/*
 * ============================================================
 * No LRC
 * ============================================================
 */

if (!is_file($lrcPath)) {
    respond([
        'exists' => false,
        'file' =>
            basename(
                $lrcPath
            )
    ]);
}


/*
 * ============================================================
 * Read LRC
 * ============================================================
 */

$content =
    file_get_contents(
        $lrcPath
    );


if ($content === false) {
    respond([
        'error' =>
            'No fue posible leer el archivo LRC.'
    ], 500);
}


respond([
    'exists' => true,

    'file' =>
        basename(
            $lrcPath
        ),

    'size' =>
        filesize(
            $lrcPath
        ),

    'mtime' =>
        filemtime(
            $lrcPath
        ),

    'content' =>
        $content
]);