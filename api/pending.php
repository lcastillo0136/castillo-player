<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';

function pendingHelper(
    string $name
): string {
    $allowed = [
        'castillo-pending-change',
        'castillo-pending-check',
        'castillo-pending-sync'
    ];


    if (
        !in_array(
            $name,
            $allowed,
            true
        )
    ) {
        jsonResponse([
            'error' =>
                'Helper NAS no permitido.'
        ], 500);
    }


    $path =
        rtrim(
            CASTILLO_HELPER_DIR,
            '/'
        ) .
        '/' .
        $name;


    if (
        !is_file($path) ||
        !is_executable($path)
    ) {
        jsonResponse([
            'error' =>
                'El helper requerido del módulo NAS no está disponible.'
        ], 503);
    }


    return $path;
}


function runJsonCommand(
    array $command,
    int $errorStatus = 500
): array {
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
                'No fue posible iniciar la operación.'
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


    $stdout =
        trim(
            $stdout
        );

    $stderr =
        trim(
            $stderr
        );


    $result = null;


    if ($stdout !== '') {
        $decoded =
            json_decode(
                $stdout,
                true
            );


        if (
            is_array(
                $decoded
            )
        ) {
            $result =
                $decoded;
        }
    }


    if (
        $result === null &&
        $stderr !== ''
    ) {
        $decoded =
            json_decode(
                $stderr,
                true
            );


        if (
            is_array(
                $decoded
            )
        ) {
            $result =
                $decoded;
        }
    }


    if ($status !== 0) {
        if (
            is_array(
                $result
            )
        ) {
            jsonResponse(
                $result,
                $errorStatus
            );
        }


        jsonResponse([
            'error' =>
                $stderr
                ?: $stdout
                ?: 'La operación terminó con error.'
        ], $errorStatus);
    }


    if (
        !is_array(
            $result
        )
    ) {
        jsonResponse([
            'error' =>
                'La operación devolvió una respuesta inválida.'
        ], 500);
    }


    return $result;
}


function normalizeFiles(
    mixed $files
): array {
    if (
        !is_array(
            $files
        )
    ) {
        jsonResponse([
            'error' =>
                'La lista de archivos no es válida.'
        ], 400);
    }


    $result = [];


    foreach (
        $files
        as $file
    ) {
        if (
            !is_string(
                $file
            )
        ) {
            continue;
        }


        $file =
            trim(
                $file
            );


        if (
            $file === ''
        ) {
            continue;
        }


        $result[] =
            $file;
    }


    $result =
        array_values(
            array_unique(
                $result
            )
        );


    if (!$result) {
        jsonResponse([
            'error' =>
                'No hay archivos seleccionados.'
        ], 400);
    }


    if (
        count(
            $result
        ) > 500
    ) {
        jsonResponse([
            'error' =>
                'Hay demasiados archivos seleccionados.'
        ], 400);
    }


    return $result;
}


function pendingList(): array {
    return runJsonCommand([
        '/usr/bin/sudo',
        '-n',

        pendingHelper(
            'castillo-pending-change'
        ),

        'list'
    ]);
}


function pendingCheck(
    array $files
): array {
    $command = [
        '/usr/bin/sudo',
        '-n',

        pendingHelper(
            'castillo-pending-check'
        )
    ];


    foreach (
        $files
        as $file
    ) {
        $command[] =
            '--relative';

        $command[] =
            $file;
    }


    return runJsonCommand(
        $command,
        409
    );
}


function pendingSync(
    array $files
): array {
    $command = [
        '/usr/bin/sudo',
        '-n',

        pendingHelper(
            'castillo-pending-sync'
        )
    ];


    foreach (
        $files
        as $file
    ) {
        $command[] =
            '--relative';

        $command[] =
            $file;
    }


    /*
     * Una transferencia real puede tardar
     * más que una petición PHP normal.
     */
    @set_time_limit(
        300
    );


    return runJsonCommand(
        $command,
        409
    );
}


$method =
    $_SERVER['REQUEST_METHOD']
    ?? 'GET';

/*
 * El módulo NAS es opcional.
 *
 * Con NAS deshabilitado, GET devuelve
 * un estado vacío válido para que el
 * frontend no tenga que tratarlo como
 * un fallo del reproductor.
 */
if (!CASTILLO_NAS_ENABLED) {
    if ($method === 'GET') {
        jsonResponse([
            'ok' => true,
            'enabled' => false,
            'version' => 1,
            'items' => [],
            'count' => 0
        ]);
    }


    jsonResponse([
        'ok' => false,
        'enabled' => false,
        'error' =>
            'El módulo NAS está deshabilitado.'
    ], 409);
}

if ($method === 'GET') {
    jsonResponse(
        pendingList()
    );
}


if ($method === 'POST') {
    $raw =
        file_get_contents(
            'php://input'
        );


    $data =
        json_decode(
            $raw ?: '{}',
            true
        );


    if (
        !is_array(
            $data
        )
    ) {
        jsonResponse([
            'error' =>
                'JSON inválido.'
        ], 400);
    }


    $action =
        (string) (
            $data['action']
            ?? ''
        );


    if (
        $action === 'check'
    ) {
        $files =
            normalizeFiles(
                $data['files']
                ?? []
            );


        jsonResponse(
            pendingCheck(
                $files
            )
        );
    }


    if (
        $action === 'sync'
    ) {
        $files =
            normalizeFiles(
                $data['files']
                ?? []
            );


        jsonResponse(
            pendingSync(
                $files
            )
        );
    }


    jsonResponse([
        'error' =>
            'Acción no permitida.'
    ], 400);
}


jsonResponse([
    'error' =>
        'Método no permitido.'
], 405);