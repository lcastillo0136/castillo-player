<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';

function registerPendingChange(
    string $localPath,
    string $type,
    string $audioFile = ''
): void {
    if (!CASTILLO_NAS_ENABLED) {
        return;
    }

    if (
        !in_array(
            $type,
            [
                'lyrics',
                'tags',
                'artwork'
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Tipo de cambio pendiente inválido.'
        );
    }

    $helper =
        rtrim(
            CASTILLO_HELPER_DIR,
            '/'
        ) .
        '/castillo-pending-change';


    if (
        !is_file($helper) ||
        !is_executable($helper)
    ) {
        throw new RuntimeException(
            'El helper de cambios pendientes no está disponible.'
        );
    }


    $command = [
        '/usr/bin/sudo',
        '-n',

        $helper,

        'register',

        '--local',
        $localPath,

        '--type',
        $type
    ];


    if ($audioFile !== '') {
        $command[] =
            '--audio-file';

        $command[] =
            $audioFile;
    }


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
        throw new RuntimeException(
            'No fue posible registrar el cambio pendiente.'
        );
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


        throw new RuntimeException(
            $error['error']
            ?? trim($stderr)
            ?: 'No fue posible registrar el cambio pendiente.'
        );
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
        throw new RuntimeException(
            'Respuesta inválida del registro de cambios.'
        );
    }
}