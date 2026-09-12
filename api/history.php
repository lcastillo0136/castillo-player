<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';


const CASTILLO_HISTORY_LIMIT = 100;


function loadHistory(): array
{
    if (!is_file(CASTILLO_HISTORY)) {
        return [];
    }

    $raw = file_get_contents(
        CASTILLO_HISTORY
    );

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode(
        $raw,
        true
    );

    if (
        !is_array($data) ||
        !isset($data['items']) ||
        !is_array($data['items'])
    ) {
        return [];
    }

    return $data['items'];
}


function saveHistory(
    array $items
): void {
    $payload = [
        'version' => 1,
        'items' => array_values(
            array_slice(
                $items,
                0,
                CASTILLO_HISTORY_LIMIT
            )
        )
    ];

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_PRETTY_PRINT
    );

    if ($json === false) {
        jsonResponse([
            'error' =>
                'No fue posible codificar el historial.'
        ], 500);
    }

    $result = file_put_contents(
        CASTILLO_HISTORY,
        $json . PHP_EOL,
        LOCK_EX
    );

    if ($result === false) {
        jsonResponse([
            'error' =>
                'No fue posible guardar el historial.'
        ], 500);
    }
}


$method =
    strtoupper(
        $_SERVER['REQUEST_METHOD']
        ?? 'GET'
    );


/*
 * GET
 * Devuelve historial.
 */
if ($method === 'GET') {
    jsonResponse([
        'ok' => true,
        'items' => loadHistory()
    ]);
}


/*
 * POST
 * Registra la canción que MPD
 * realmente tiene seleccionada.
 */
if ($method === 'POST') {
    $current =
        mpdObject(
            'currentsong'
        );

    $file =
        $current['file'] ?? '';


    if (
        !is_string($file) ||
        $file === ''
    ) {
        jsonResponse([
            'ok' => true,
            'items' => loadHistory()
        ]);
    }


    /*
     * Solo música local de Castillo.
     */
    if (
        !str_starts_with(
            $file,
            CASTILLO_LIBRARY_MPD_PREFIX
        )
    ) {
        jsonResponse([
            'ok' => true,
            'items' => loadHistory()
        ]);
    }


    $item = [
        'file' =>
            $file,

        'title' =>
            $current['Title']
            ?? $current['title']
            ?? basename($file),

        'artist' =>
            $current['Artist']
            ?? $current['artist']
            ?? '',

        'album' =>
            $current['Album']
            ?? $current['album']
            ?? '',

        'duration' =>
            isset($current['duration'])
                ? (float) $current['duration']
                : 0,

        'played_at' =>
            time()
    ];


    $items =
        loadHistory();


    /*
     * Quitamos aparición anterior.
     * Así el historial representa
     * canciones recientes únicas.
     */
    $items = array_values(
        array_filter(
            $items,
            static fn ($existing) =>
                !is_array($existing) ||
                ($existing['file'] ?? '')
                    !== $file
        )
    );


    array_unshift(
        $items,
        $item
    );


    saveHistory(
        $items
    );


    jsonResponse([
        'ok' => true,
        'items' => $items
    ]);
}


jsonResponse([
    'error' =>
        'Método no permitido.'
], 405);