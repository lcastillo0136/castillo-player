<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';


/*
 * Castillo Player
 * MPD -> Server-Sent Events
 */

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');

/*
 * Evita buffering de nginx.
 */
header('X-Accel-Buffering: no');

set_time_limit(0);
ignore_user_abort(true);


/*
 * Desactivar buffers de PHP.
 */
while (ob_get_level() > 0) {
    @ob_end_flush();
}

ob_implicit_flush(true);


/*
 * Enviar evento SSE.
 */
function sendEvent(
    string $event,
    array $data
): void {
    echo 'event: ' . $event . "\n";

    echo 'data: ' . json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    ) . "\n\n";

    @flush();
}


/*
 * Enviar estado actual completo.
 */
function sendPlayerState(
    array $changed
): void {
    try {
        sendEvent(
            'mpd',
            [
                'changed' => $changed,

                'status' =>
                    mpdObject('status'),

                'song' =>
                    mpdObject('currentsong')
            ]
        );
    } catch (Throwable $error) {
        sendEvent(
            'castillo-error',
            [
                'message' =>
                    $error->getMessage()
            ]
        );
    }
}


/*
 * Conexión permanente para MPD idle.
 */
$errno = 0;
$errstr = '';

$socket = @stream_socket_client(
    'tcp://' . MPD_HOST . ':' . MPD_PORT,
    $errno,
    $errstr,
    5
);

if (!$socket) {
    sendEvent(
        'castillo-error',
        [
            'message' =>
                "No fue posible conectar con MPD: $errstr"
        ]
    );

    exit;
}


/*
 * Leemos saludo de MPD.
 */
$hello = fgets($socket);

if (
    $hello === false ||
    !str_starts_with($hello, 'OK MPD')
) {
    sendEvent(
        'castillo-error',
        [
            'message' =>
                'MPD no devolvió un saludo válido.'
        ]
    );

    fclose($socket);
    exit;
}


/*
 * Reintento automático de EventSource
 * si la conexión se corta.
 */
echo "retry: 2000\n\n";
flush();


/*
 * Primer estado al abrir Castillo Player.
 */
sendPlayerState([
    'initial'
]);


/*
 * Esperaremos máximo 25 segundos.
 *
 * Si no cambia nada, cancelamos temporalmente
 * idle y enviamos heartbeat para mantener
 * viva la conexión nginx/PHP/browser.
 */
stream_set_timeout(
    $socket,
    25
);


/*
 * Subsistemas MPD que nos interesan.
 */
$idleCommand =
    'idle player mixer options playlist database update';


while (
    !connection_aborted() &&
    !feof($socket)
) {
    fwrite(
        $socket,
        $idleCommand . "\n"
    );

    $changed = [];
    $timedOut = false;


    while (true) {
        $line = fgets($socket);

        if ($line === false) {
            $meta =
                stream_get_meta_data(
                    $socket
                );

            if (
                !empty($meta['timed_out'])
            ) {
                $timedOut = true;
            }

            break;
        }

        $line =
            rtrim(
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
            sendEvent(
                'castillo-error',
                [
                    'message' => $line
                ]
            );

            fclose($socket);
            exit;
        }


        if (
            str_starts_with(
                $line,
                'changed: '
            )
        ) {
            $changed[] =
                substr(
                    $line,
                    9
                );
        }
    }


    /*
     * MPD sigue dentro de idle si ocurrió
     * timeout de lectura.
     *
     * noidle lo despierta.
     */
    if ($timedOut) {
        fwrite(
            $socket,
            "noidle\n"
        );

        /*
         * Consumir respuesta hasta OK.
         */
        while (
            ($line = fgets($socket))
            !== false
        ) {
            $line =
                rtrim(
                    $line,
                    "\r\n"
                );

            if ($line === 'OK') {
                break;
            }
        }


        /*
         * Heartbeat SSE.
         * Los comentarios ":" son ignorados
         * por EventSource.
         */
        echo ": heartbeat\n\n";
        flush();

        continue;
    }


    /*
     * Solo enviamos estado cuando MPD
     * realmente reportó cambios.
     */
    if (!empty($changed)) {
        sendPlayerState(
            array_values(
                array_unique($changed)
            )
        );
    }
}


fclose($socket);