<?php

declare(strict_types=1);


require_once __DIR__ . '/core.php';


/*
 * ============================================================
 * Optional NAS / pending integration
 * ============================================================
 *
 * La edición de artwork pertenece al Core de Castillo.
 *
 * El registro de cambios para sincronización NAS solamente
 * se carga cuando dicho módulo está habilitado.
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


const MAX_ARTWORK_SIZE =
    10 * 1024 * 1024;


/*
 * ============================================================
 * Resolve logical MPD file -> physical audio file
 * ============================================================
 */

function resolveLocalAudio(
    string $file
): string {
    if ($file === '') {
        jsonResponse([
            'error' =>
                'No se proporcionó archivo.'
        ], 400);
    }


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
     * Raíz física configurada para la biblioteca
     * administrada por Castillo.
     */

    $root =
        realpath(
            CASTILLO_LIBRARY_ROOT
        );


    if (
        $root === false ||
        !is_dir($root)
    ) {
        jsonResponse([
            'error' =>
                'No se encontró la biblioteca configurada.'
        ], 500);
    }


    /*
     * Eliminamos el prefijo lógico MPD para obtener
     * la ruta relativa dentro de library_root.
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


    $candidate =
        rtrim(
            $root,
            '/'
        ) .
        '/' .
        $relativePath;


    $real =
        realpath(
            $candidate
        );


    if (
        $real === false ||
        !is_file($real)
    ) {
        jsonResponse([
            'error' =>
                'No se encontró el archivo de audio.'
        ], 404);
    }


    /*
     * ========================================================
     * Security boundary
     * ========================================================
     *
     * realpath() resuelve enlaces simbólicos y segmentos ../.
     * Después comprobamos que el archivo final siga dentro
     * de la biblioteca autorizada.
     */

    $allowedPrefix =
        rtrim(
            $root,
            '/'
        ) .
        '/';


    if (
        !str_starts_with(
            $real,
            $allowedPrefix
        )
    ) {
        jsonResponse([
            'error' =>
                'Ruta no autorizada.'
        ], 403);
    }


    return $real;
}


/*
 * ============================================================
 * External artwork
 * ============================================================
 */

function externalArtwork(
    string $audio
): ?string {
    $directory =
        dirname(
            $audio
        );


    $names = [
        'cover.jpg',
        'cover.jpeg',
        'cover.png',
        'folder.jpg',
        'folder.jpeg',
        'folder.png',
    ];


    foreach (
        $names
        as $name
    ) {
        $candidate =
            $directory .
            DIRECTORY_SEPARATOR .
            $name;


        if (
            is_file(
                $candidate
            )
        ) {
            return $candidate;
        }
    }


    return null;
}


/*
 * ============================================================
 * Embedded artwork detection
 * ============================================================
 */

function hasEmbeddedArtwork(
    string $audio
): bool {
    $script = <<<'PY'
import sys

from mutagen import File


path = sys.argv[1]

audio = File(
    path,
    easy=False
)


if audio is None:
    print("0")
    raise SystemExit


name = type(audio).__name__


if name == "MP3":
    tags = getattr(
        audio,
        "tags",
        None
    )

    if tags:
        for key in tags.keys():
            if str(key).startswith("APIC"):
                print("1")
                raise SystemExit


elif name == "FLAC":
    pictures = getattr(
        audio,
        "pictures",
        []
    )

    if pictures:
        print("1")
        raise SystemExit


elif name in (
    "MP4",
    "M4A",
):
    tags = getattr(
        audio,
        "tags",
        None
    )

    if tags and tags.get("covr"):
        print("1")
        raise SystemExit


print("0")
PY;


    $command = [
        '/usr/bin/python3',
        '-c',
        $script,
        $audio
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
        return false;
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


    return (
        $status === 0 &&
        trim($stdout) === '1'
    );
}


/*
 * ============================================================
 * Cover helper
 * ============================================================
 */

function runCoverHelper(
    array $arguments
): array {
    if (
        !is_file(
            CASTILLO_COVER_HELPER
        ) ||
        !is_executable(
            CASTILLO_COVER_HELPER
        )
    ) {
        jsonResponse([
            'error' =>
                'El helper de edición de portada no está disponible.'
        ], 500);
    }


    $command = [
        '/usr/bin/sudo',
        '-n',

        CASTILLO_COVER_HELPER,

        ...$arguments
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
                'No fue posible iniciar el editor de portada.'
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
        $error =
            json_decode(
                trim($stderr),
                true
            );


        jsonResponse([
            'error' =>
                $error['error']
                ?? trim($stderr)
                ?: 'No fue posible modificar la portada.'
        ], 500);
    }


    $result =
        json_decode(
            trim($stdout),
            true
        );


    if (
        !is_array($result) ||
        empty($result['ok'])
    ) {
        jsonResponse([
            'error' =>
                'Respuesta inválida del editor de portada.'
        ], 500);
    }


    return $result;
}


/*
 * ============================================================
 * Artwork state
 * ============================================================
 */

function artworkState(
    string $file,
    string $audio
): array {
    $embedded =
        hasEmbeddedArtwork(
            $audio
        );


    $external =
        externalArtwork(
            $audio
        );


    $source =
        $embedded
            ? 'embedded'
            : (
                $external !== null
                    ? 'external'
                    : 'none'
            );


    return [
        'ok' => true,

        'file' =>
            $file,

        'source' =>
            $source,

        'embedded' =>
            $embedded,

        'external' =>
            $external !== null,

        /*
         * Usamos el proveedor de portadas de moOde
         * que ya utiliza Castillo Player.
         *
         * Esta dependencia se revisará posteriormente
         * dentro de la auditoría de compatibilidad moOde.
         */
        'url' =>
            '/coverart.php/' .
            rawurlencode(
                $file
            ) .
            '?v=' .
            filemtime(
                $audio
            ),
    ];
}


/*
 * ============================================================
 * Request method
 * ============================================================
 */

$method =
    $_SERVER['REQUEST_METHOD']
    ?? 'GET';


/*
 * ============================================================
 * GET
 * Estado de portada
 * ============================================================
 */

if ($method === 'GET') {
    $file =
        trim(
            (string) (
                $_GET['file']
                ?? ''
            )
        );


    $audio =
        resolveLocalAudio(
            $file
        );


    jsonResponse(
        artworkState(
            $file,
            $audio
        )
    );
}


/*
 * ============================================================
 * POST
 * Nueva portada embebida
 * ============================================================
 */

if ($method === 'POST') {
    $file =
        trim(
            (string) (
                $_POST['file']
                ?? ''
            )
        );


    $audio =
        resolveLocalAudio(
            $file
        );


    if (
        !isset(
            $_FILES['image']
        )
    ) {
        jsonResponse([
            'error' =>
                'No se recibió ninguna imagen.'
        ], 400);
    }


    $upload =
        $_FILES['image'];


    if (
        (
            $upload['error']
            ?? UPLOAD_ERR_NO_FILE
        )
        !== UPLOAD_ERR_OK
    ) {
        jsonResponse([
            'error' =>
                'No fue posible recibir la imagen.'
        ], 400);
    }


    $size =
        (int) (
            $upload['size']
            ?? 0
        );


    if (
        $size <= 0 ||
        $size > MAX_ARTWORK_SIZE
    ) {
        jsonResponse([
            'error' =>
                'La imagen debe pesar como máximo 10 MB.'
        ], 413);
    }


    $temporary =
        (string) (
            $upload['tmp_name']
            ?? ''
        );


    if (
        $temporary === '' ||
        !is_uploaded_file(
            $temporary
        )
    ) {
        jsonResponse([
            'error' =>
                'Archivo temporal inválido.'
        ], 400);
    }


    runCoverHelper([
        '--audio',
        $audio,

        '--image',
        $temporary
    ]);


    /*
     * Registrar el cambio solamente cuando
     * el módulo NAS esté disponible.
     *
     * La edición local ya terminó correctamente;
     * un error en Pending no invalida esa edición.
     */

    if (
        CASTILLO_NAS_ENABLED &&
        function_exists(
            'registerPendingChange'
        )
    ) {
        try {
            registerPendingChange(
                $audio,
                'artwork',
                $file
            );

        } catch (Throwable $error) {
            error_log(
                '[Castillo pending] Artwork: ' .
                $error->getMessage()
            );
        }
    }


    clearstatcache(
        true,
        $audio
    );


    jsonResponse(
        artworkState(
            $file,
            $audio
        )
    );
}


/*
 * ============================================================
 * DELETE
 * Eliminar únicamente portada embebida
 * ============================================================
 */

if ($method === 'DELETE') {
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


    $audio =
        resolveLocalAudio(
            $file
        );


    runCoverHelper([
        '--audio',
        $audio,
        '--remove'
    ]);


    if (
        CASTILLO_NAS_ENABLED &&
        function_exists(
            'registerPendingChange'
        )
    ) {
        try {
            registerPendingChange(
                $audio,
                'artwork',
                $file
            );

        } catch (Throwable $error) {
            error_log(
                '[Castillo pending] Artwork: ' .
                $error->getMessage()
            );
        }
    }


    clearstatcache(
        true,
        $audio
    );


    jsonResponse(
        artworkState(
            $file,
            $audio
        )
    );
}


jsonResponse([
    'error' =>
        'Método no permitido.'
], 405);