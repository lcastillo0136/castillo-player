<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';

/*
 * Castillo Player
 * Audio output API
 */


function respond(
    array $data,
    int $status = 200
): never {
    http_response_code($status);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    header(
        'Cache-Control: no-store'
    );

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
 * ============================================================
 * moOde integration
 * ============================================================
 *
 * Estos archivos pertenecen a moOde.
 * Su raíz se obtiene de castillo.ini mediante MOODE_WWW_ROOT.
 */

$moodeIncludes = [
    MOODE_WWW_ROOT . '/inc/audio.php',
    MOODE_WWW_ROOT . '/inc/common.php',
    MOODE_WWW_ROOT . '/inc/mpd.php',
    MOODE_WWW_ROOT . '/inc/session.php',
    MOODE_WWW_ROOT . '/inc/sql.php',
];


foreach ($moodeIncludes as $moodeInclude) {
    if (!is_file($moodeInclude)) {
        respond([
            'error' =>
                'No se encontró una dependencia requerida de moOde: ' .
                basename($moodeInclude)
        ], 500);
    }


    require_once $moodeInclude;
}


unset(
    $moodeIncludes,
    $moodeInclude
);


function runCommand(
    array $command
): array {
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    $process = proc_open(
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
        return [
            'code' => 1,
            'stdout' => '',
            'stderr' =>
                'Could not execute command.'
        ];
    }

    fclose($pipes[0]);

    $stdout =
        stream_get_contents(
            $pipes[1]
        );

    fclose($pipes[1]);

    $stderr =
        stream_get_contents(
            $pipes[2]
        );

    fclose($pipes[2]);

    $code =
        proc_close(
            $process
        );

    return [
        'code' => $code,
        'stdout' => trim($stdout),
        'stderr' => trim($stderr)
    ];
}

function runAudioOutputHelper(
    array $arguments
): array {
    if (
        !is_file(
            CASTILLO_AUDIO_OUTPUT_HELPER
        ) ||
        !is_executable(
            CASTILLO_AUDIO_OUTPUT_HELPER
        )
    ) {
        return [
            'code' => 1,
            'stdout' => '',
            'stderr' =>
                'El helper de salida de audio de Castillo no está disponible.'
        ];
    }


    return runCommand([
        '/usr/bin/sudo',
        '-n',

        CASTILLO_AUDIO_OUTPUT_HELPER,

        ...$arguments
    ]);
}

function captureMpdState(): array
{
    $result =
        runCommand([
            '/usr/bin/mpc',
            'status'
        ]);


    $state = 'stop';

    if (
        str_contains(
            $result['stdout'],
            '[playing]'
        )
    ) {
        $state = 'play';

    } elseif (
        str_contains(
            $result['stdout'],
            '[paused]'
        )
    ) {
        $state = 'pause';
    }


    /*
     * Ejemplo de mpc:
     *
     * [playing] #1575/3215  2:04/3:56
     */
    $position = null;
    $elapsed = 0.0;


    if (
        preg_match(
            '/#(\d+)\/\d+/',
            $result['stdout'],
            $match
        )
    ) {
        $position =
            (int) $match[1];
    }


    if (
        preg_match(
            '/(\d+):(\d+)\/\d+:\d+/',
            $result['stdout'],
            $match
        )
    ) {
        $elapsed =
            ((int) $match[1] * 60) +
            (int) $match[2];
    }


    return [
        'state' =>
            $state,

        'position' =>
            $position,

        'elapsed' =>
            $elapsed
    ];
}

function waitForMpd(
    int $timeoutMs = 10000
): bool {
    $started =
        microtime(true);


    while (
        (
            microtime(true) -
            $started
        ) * 1000
        < $timeoutMs
    ) {
        $result =
            runCommand([
                '/usr/bin/mpc',
                'status'
            ]);


        if (
            $result['code'] === 0
        ) {
            return true;
        }


        usleep(
            200000
        );
    }


    return false;
}

function restoreMpdState(
    array $state
): void {
    /*
     * Si estaba detenido antes
     * del cambio, lo dejamos detenido.
     */
    if (
        !in_array(
            $state['state'] ?? 'stop',
            [
                'play',
                'pause'
            ],
            true
        )
    ) {
        return;
    }


    if (!waitForMpd()) {
        return;
    }


    $position =
        $state['position']
        ?? null;


    /*
     * mpc usa posiciones de playlist
     * como las que muestra #1575/3215.
     */
    if (
        is_int($position) &&
        $position > 0
    ) {
        runCommand([
            '/usr/bin/mpc',
            'play',
            (string) $position
        ]);

    } else {
        runCommand([
            '/usr/bin/mpc',
            'play'
        ]);
    }


    /*
     * Esperamos brevemente a que
     * la nueva salida ALSA esté lista.
     */
    usleep(
        350000
    );


    $elapsed =
        (float) (
            $state['elapsed']
            ?? 0
        );


    if ($elapsed > 1) {
        runCommand([
            '/usr/bin/mpc',
            'seek',
            (string) floor(
                $elapsed
            )
        ]);
    }


    /*
     * Si antes estaba pausado,
     * volvemos inmediatamente a pause.
     */
    if (
        ($state['state'] ?? '')
        === 'pause'
    ) {
        runCommand([
            '/usr/bin/mpc',
            'pause'
        ]);
    }
}

function getMoodeOutput(): string
{
    session_id(
        phpSession(
            'get_sessionid'
        )
    );

    phpSession('open');

    $output =
        $_SESSION['audioout']
        ?? 'Local';

    phpSession('close');

    return $output;
}


function getBluetoothMac(): string
{
    $file =
        ALSA_PLUGIN_PATH .
        '/btstream.conf';

    if (!is_readable($file)) {
        return '';
    }

    $content =
        file_get_contents($file);

    if ($content === false) {
        return '';
    }

    if (
        preg_match(
            '/device\s+"([0-9A-F:]{17})"/i',
            $content,
            $match
        )
    ) {
        return strtoupper(
            $match[1]
        );
    }

    return '';
}


function bluetoothInfo(
    string $mac
): string {
    $result =
        runCommand([
            '/usr/bin/bluetoothctl',
            'info',
            $mac
        ]);

    if ($result['code'] !== 0) {
        return '';
    }

    return $result['stdout'];
}


function pairedAudioSinks(): array
{
    $result =
        runCommand([
            '/usr/bin/bluetoothctl',
            'devices',
            'Paired'
        ]);


    if ($result['code'] !== 0) {
        return [];
    }


    $devices = [];


    foreach (
        preg_split(
            '/\r?\n/',
            $result['stdout']
        )
        as $line
    ) {
        if (
            !preg_match(
                '/^Device\s+([0-9A-F:]{17})\s+(.+)$/i',
                trim($line),
                $match
            )
        ) {
            continue;
        }


        $mac =
            strtoupper(
                $match[1]
            );

        $fallbackName =
            trim(
                $match[2]
            );


        $info =
            bluetoothInfo(
                $mac
            );


        /*
         * Bluetooth Audio Sink UUID.
         */
        if (
            stripos(
                $info,
                '0000110b-0000-1000-8000-00805f9b34fb'
            ) === false
        ) {
            continue;
        }


        $name =
            $fallbackName;

        if (
            preg_match(
                '/^\s*Alias:\s*(.+)$/mi',
                $info,
                $nameMatch
            )
        ) {
            $name =
                trim(
                    $nameMatch[1]
                );
        }


        $connected =
            preg_match(
                '/^\s*Connected:\s*yes\s*$/mi',
                $info
            ) === 1;


        $devices[] = [
            'id' =>
                'bluetooth:' . $mac,

            'type' =>
                'bluetooth',

            'mac' =>
                $mac,

            'name' =>
                $name,

            'connected' =>
                $connected
        ];
    }


    return $devices;
}


function outputState(): array
{
    $mode =
        getMoodeOutput();

    $bluetoothMac =
        getBluetoothMac();


    $outputs = [
        [
            'id' =>
                'local',

            'type' =>
                'local',

            'mac' =>
                null,

            'name' =>
                'DAC HiFi',

            'connected' =>
                true
        ]
    ];


    foreach (
        pairedAudioSinks()
        as $device
    ) {
        $outputs[] =
            $device;
    }


    if (
        strcasecmp(
            $mode,
            'Bluetooth'
        ) === 0 &&
        $bluetoothMac !== ''
    ) {
        $selected =
            'bluetooth:' .
            $bluetoothMac;
    } else {
        $selected =
            'local';
    }


    return [
        'ok' => true,

        'mode' =>
            $mode,

        'selected' =>
            $selected,

        'outputs' =>
            $outputs
    ];
}


$method =
    strtoupper(
        $_SERVER['REQUEST_METHOD']
        ?? 'GET'
    );


/*
 * GET
 */
if ($method === 'GET') {
    respond(
        outputState()
    );
}


/*
 * POST
 */
if ($method !== 'POST') {
    respond([
        'error' =>
            'Método no permitido.'
    ], 405);
}


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
    respond([
        'error' =>
            'JSON inválido.'
    ], 400);
}


$type =
    $data['type']
    ?? '';


$mpdStateBefore =
    captureMpdState();


/*
 * LOCAL
 */
if ($type === 'local') {

    $result =
        runAudioOutputHelper([
            'local'
        ]);

    if ($result['code'] !== 0) {
        respond([
            'error' =>
                $result['stderr']
                ?: $result['stdout']
                ?: 'No fue posible cambiar la salida.'
        ], 500);
    }


    /*
     * set-btaudio reinicia MPD.
     * Esperamos a que vuelva.
     */
    waitForMpd();


    restoreMpdState(
        $mpdStateBefore
    );


    respond(
        outputState()
    );
}


/*
 * BLUETOOTH
 */
if ($type === 'bluetooth') {

    $mac =
        strtoupper(
            trim(
                (string) (
                    $data['mac']
                    ?? ''
                )
            )
        );


    if (
        !preg_match(
            '/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/',
            $mac
        )
    ) {
        respond([
            'error' =>
                'Dirección Bluetooth inválida.'
        ], 400);
    }


    /*
     * No aceptamos simplemente cualquier MAC.
     * Debe estar entre los Audio Sink
     * emparejados.
     */
    $allowed =
        array_filter(
            pairedAudioSinks(),

            static fn ($device) =>
                ($device['mac'] ?? '')
                === $mac
        );


    if (!$allowed) {
        respond([
            'error' =>
                'El dispositivo no es una salida Bluetooth válida.'
        ], 403);
    }


    $result =
        runAudioOutputHelper([
            'bluetooth',
            $mac
        ]);


    if ($result['code'] !== 0) {
        respond([
            'error' =>
                $result['stderr']
                ?: $result['stdout']
                ?: 'No fue posible cambiar a Bluetooth.'
        ], 500);
    }


    /*
     * Bluetooth puede necesitar algo
     * más de tiempo para quedar listo.
     */
    waitForMpd();


    /*
     * Esperamos específicamente que
     * BlueZ termine de establecer
     * la conexión.
     */
    $started =
        microtime(true);


    while (
        microtime(true) -
        $started
        < 8
    ) {
        $info =
            bluetoothInfo(
                $mac
            );


        if (
            preg_match(
                '/^\s*Connected:\s*yes\s*$/mi',
                $info
            )
        ) {
            break;
        }


        usleep(
            250000
        );
    }


    /*
     * Un pequeño margen para que
     * ALSA Bluetooth quede operativo.
     */
    usleep(
        400000
    );


    restoreMpdState(
        $mpdStateBefore
    );


    respond(
        outputState()
    );
}


respond([
    'error' =>
        'Tipo de salida desconocido.'
], 400);