<?php

declare(strict_types=1);


require_once __DIR__ . '/config.php';


function jsonResponse(
    mixed $data,
    int $status = 200
): never {
    http_response_code($status);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    header('Cache-Control: no-store');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


function mpdEscape(string $value): string
{
    $value = str_replace(
        ['\\', '"'],
        ['\\\\', '\\"'],
        $value
    );

    return '"' . $value . '"';
}


function mpdRaw(string $command): array
{
    $errno = 0;
    $errstr = '';

    $socket = @stream_socket_client(
        'tcp://' . MPD_HOST . ':' . MPD_PORT,
        $errno,
        $errstr,
        3
    );

    if (!$socket) {
        throw new RuntimeException(
            "No fue posible conectar con MPD: $errstr"
        );
    }

    stream_set_timeout(
        $socket,
        3
    );

    $hello = fgets($socket);

    if (
        $hello === false ||
        !str_starts_with(
            $hello,
            'OK MPD'
        )
    ) {
        fclose($socket);

        throw new RuntimeException(
            'MPD no devolvió un saludo válido.'
        );
    }

    fwrite(
        $socket,
        $command . "\n"
    );

    $result = [];

    while (
        ($line = fgets($socket))
        !== false
    ) {
        $line = rtrim(
            $line,
            "\r\n"
        );

        if ($line === 'OK') {
            break;
        }

        if (
            str_starts_with(
                $line,
                'ACK'
            )
        ) {
            fclose($socket);

            throw new RuntimeException(
                'MPD: ' . $line
            );
        }

        $result[] = $line;
    }

    fclose($socket);

    return $result;
}


function mpdObject(
    string $command
): array {
    $result = [];

    foreach (
        mpdRaw($command)
        as $line
    ) {
        $separator =
            strpos(
                $line,
                ': '
            );

        if ($separator === false) {
            continue;
        }

        $key =
            strtolower(
                substr(
                    $line,
                    0,
                    $separator
                )
            );

        $value =
            substr(
                $line,
                $separator + 2
            );

        $result[$key] =
            $value;
    }

    return $result;
}


function mpdSongs(
    string $command
): array {
    $songs = [];
    $current = null;

    foreach (
        mpdRaw($command)
        as $line
    ) {
        $separator =
            strpos(
                $line,
                ': '
            );

        if ($separator === false) {
            continue;
        }

        $rawKey =
            substr(
                $line,
                0,
                $separator
            );

        $key =
            strtolower(
                $rawKey
            );

        $value =
            substr(
                $line,
                $separator + 2
            );

        if ($key === 'file') {
            if (
                $current !== null &&
                isset(
                    $current['file']
                )
            ) {
                $songs[] =
                    $current;
            }

            $current = [
                'file' => $value
            ];

            continue;
        }

        if ($current !== null) {
            $current[$key] =
                $value;
        }
    }

    if (
        $current !== null &&
        isset(
            $current['file']
        )
    ) {
        $songs[] =
            $current;
    }

    return $songs;
}


function localSongs(
    array $songs
): array {
    return array_values(
        array_filter(
            $songs,
            fn(array $song): bool =>
                isset(
                    $song['file']
                ) &&
                str_starts_with(
                    $song['file'],
                    CASTILLO_LIBRARY_MPD_PREFIX
                )
        )
    );
}


function getLibrary(): array
{
    if (
        is_file(
            CASTILLO_LIBRARY_CACHE
        ) &&
        filemtime(
            CASTILLO_LIBRARY_CACHE
        ) >
            time() - 30
    ) {
        $cached =
            json_decode(
                (string)
                file_get_contents(
                    CASTILLO_LIBRARY_CACHE
                ),
                true
            );

        if (
            is_array(
                $cached
            )
        ) {
            return $cached;
        }
    }


    $songs =
        localSongs(
            mpdSongs(
                'listallinfo ' .
                mpdEscape(
                    rtrim(
                        CASTILLO_LIBRARY_MPD_PREFIX,
                        '/'
                    )
                )
            )
        );


    usort(
        $songs,
        function (
            array $a,
            array $b
        ): int {
            $artistA =
                $a['artist']
                ?? '';

            $artistB =
                $b['artist']
                ?? '';


            $cmp =
                strnatcasecmp(
                    $artistA,
                    $artistB
                );


            if ($cmp !== 0) {
                return $cmp;
            }


            return strnatcasecmp(
                $a['title']
                    ?? basename(
                        $a['file']
                    ),
                $b['title']
                    ?? basename(
                        $b['file']
                    )
            );
        }
    );


    @file_put_contents(
        CASTILLO_LIBRARY_CACHE,
        json_encode(
            $songs,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        )
    );


    return $songs;
}


function loadCastilloState(): array
{
    $default = [
        'favorites' => [],
        'playlists' => []
    ];


    if (
        !is_file(
            CASTILLO_STATE
        )
    ) {
        return $default;
    }


    $data =
        json_decode(
            (string)
            file_get_contents(
                CASTILLO_STATE
            ),
            true
        );


    if (!is_array($data)) {
        return $default;
    }


    $data['favorites'] =
        is_array(
            $data['favorites']
            ?? null
        )
            ? $data['favorites']
            : [];


    $data['playlists'] =
        is_array(
            $data['playlists']
            ?? null
        )
            ? $data['playlists']
            : [];


    return $data;
}


function saveCastilloState(
    array $state
): void {
    $directory =
        dirname(
            CASTILLO_STATE
        );


    $temporary =
        tempnam(
            $directory,
            '.castillo-state-'
        );


    if ($temporary === false) {
        throw new RuntimeException(
            'No fue posible crear el estado temporal.'
        );
    }


    $json =
        json_encode(
            $state,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_PRETTY_PRINT
        );


    if ($json === false) {
        @unlink(
            $temporary
        );

        throw new RuntimeException(
            'No fue posible serializar el estado.'
        );
    }


    file_put_contents(
        $temporary,
        $json,
        LOCK_EX
    );


    chmod(
        $temporary,
        0640
    );


    rename(
        $temporary,
        CASTILLO_STATE
    );
}


function validLocalFile(
    string $file
): bool {
    return str_starts_with(
        $file,
        CASTILLO_LIBRARY_MPD_PREFIX
    );
}


function requestData(): array
{
    if (
        (
            $_SERVER[
                'REQUEST_METHOD'
            ]
            ?? 'GET'
        )
        === 'POST'
    ) {
        $raw =
            file_get_contents(
                'php://input'
            );


        $data =
            json_decode(
                $raw ?: '{}',
                true
            );


        return is_array($data)
            ? $data
            : [];
    }


    return $_GET;
}