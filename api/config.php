<?php

declare(strict_types=1);


/*
 * ============================================================
 * Castillo Player
 * Configuration loader
 * ============================================================
 *
 * La configuración específica de cada instalación vive en:
 *
 *   /etc/castillo-player/castillo.ini
 *
 * Este archivo sí forma parte del código de Castillo Player.
 * castillo.ini será creado por el instalador.
 */


const CASTILLO_CONFIG_FILE =
    '/etc/castillo-player/castillo.ini';


function castilloConfigLoad(): array
{
    if (!is_file(CASTILLO_CONFIG_FILE)) {
        throw new RuntimeException(
            'No existe la configuración de Castillo Player: ' .
            CASTILLO_CONFIG_FILE
        );
    }


    $config = parse_ini_file(
        CASTILLO_CONFIG_FILE,
        true,
        INI_SCANNER_TYPED
    );


    if (!is_array($config)) {
        throw new RuntimeException(
            'No fue posible leer la configuración de Castillo Player.'
        );
    }


    return $config;
}


function castilloConfigString(
    array $config,
    string $section,
    string $key,
    ?string $default = null,
    bool $allowEmpty = false
): string {
    $value =
        $config[$section][$key]
        ?? $default;


    if ($value === null) {
        throw new RuntimeException(
            "Falta configuración: [$section] $key"
        );
    }


    if (
        !is_string($value) &&
        !is_int($value) &&
        !is_float($value) &&
        !is_bool($value)
    ) {
        throw new RuntimeException(
            "Configuración inválida: [$section] $key"
        );
    }


    if (is_bool($value)) {
        $value =
            $value
                ? '1'
                : '0';
    }


    $value =
        trim(
            (string) $value
        );


    if (
        !$allowEmpty &&
        $value === ''
    ) {
        throw new RuntimeException(
            "Configuración vacía: [$section] $key"
        );
    }


    return $value;
}


function castilloConfigInt(
    array $config,
    string $section,
    string $key,
    int $default
): int {
    $value =
        $config[$section][$key]
        ?? $default;


    if (
        !is_int($value) &&
        !is_string($value)
    ) {
        throw new RuntimeException(
            "Configuración numérica inválida: [$section] $key"
        );
    }


    if (
        filter_var(
            $value,
            FILTER_VALIDATE_INT
        ) === false
    ) {
        throw new RuntimeException(
            "Configuración numérica inválida: [$section] $key"
        );
    }


    return (int) $value;
}


function castilloConfigBool(
    array $config,
    string $section,
    string $key,
    bool $default = false
): bool {
    $value =
        $config[$section][$key]
        ?? $default;


    if (is_bool($value)) {
        return $value;
    }


    if (is_int($value)) {
        return $value !== 0;
    }


    $normalized =
        strtolower(
            trim(
                (string) $value
            )
        );


    if (
        in_array(
            $normalized,
            [
                '1',
                'true',
                'yes',
                'on'
            ],
            true
        )
    ) {
        return true;
    }


    if (
        in_array(
            $normalized,
            [
                '0',
                'false',
                'no',
                'off',
                ''
            ],
            true
        )
    ) {
        return false;
    }


    throw new RuntimeException(
        "Configuración booleana inválida: [$section] $key"
    );
}


function castilloNormalizeAbsolutePath(
    string $path,
    string $name
): string {
    $path =
        trim(
            str_replace(
                '\\',
                '/',
                $path
            )
        );


    if (
        $path === '' ||
        !str_starts_with(
            $path,
            '/'
        )
    ) {
        throw new RuntimeException(
            "$name debe ser una ruta absoluta."
        );
    }


    $normalized =
        preg_replace(
            '#/+#',
            '/',
            $path
        );


    if (is_string($normalized)) {
        $path = $normalized;
    }


    if ($path !== '/') {
        $path =
            rtrim(
                $path,
                '/'
            );
    }


    return $path;
}


function castilloNormalizeOptionalPath(
    string $path,
    string $name
): string {
    $path =
        trim($path);


    if ($path === '') {
        return '';
    }


    return castilloNormalizeAbsolutePath(
        $path,
        $name
    );
}


function castilloNormalizeMpdPrefix(
    string $prefix
): string {
    $prefix =
        str_replace(
            '\\',
            '/',
            trim($prefix)
        );


    /*
     * MPD usa rutas lógicas relativas
     * a music_directory.
     */
    $prefix =
        trim(
            $prefix,
            "/ \t\n\r\0\x0B"
        );


    if ($prefix === '') {
        throw new RuntimeException(
            'library_mpd_prefix no puede estar vacío.'
        );
    }


    $normalized =
        preg_replace(
            '#/+#',
            '/',
            $prefix
        );


    if (is_string($normalized)) {
        $prefix = $normalized;
    }


    return $prefix . '/';
}


/*
 * ============================================================
 * Load configuration
 * ============================================================
 */

$castilloConfig =
    castilloConfigLoad();

$castilloInstallDir =
    castilloNormalizeAbsolutePath(
        castilloConfigString(
            $castilloConfig,
            'core',
            'install_dir',
            '/opt/castillo-player'
        ),
        'install_dir'
    );

$castilloLibraryRoot =
    castilloNormalizeAbsolutePath(
        castilloConfigString(
            $castilloConfig,
            'core',
            'library_root'
        ),
        'library_root'
    );


if ($castilloLibraryRoot === '/') {
    throw new RuntimeException(
        'library_root no puede ser /.'
    );
}


$castilloLibraryMpdPrefix =
    castilloNormalizeMpdPrefix(
        castilloConfigString(
            $castilloConfig,
            'core',
            'library_mpd_prefix'
        )
    );


$castilloStateDir =
    castilloNormalizeAbsolutePath(
        castilloConfigString(
            $castilloConfig,
            'core',
            'state_dir',
            '/var/lib/castillo-player'
        ),
        'state_dir'
    );


$castilloHelperDir =
    castilloNormalizeAbsolutePath(
        castilloConfigString(
            $castilloConfig,
            'core',
            'helper_dir',
            '/usr/local/sbin'
        ),
        'helper_dir'
    );


$castilloMpdHost =
    castilloConfigString(
        $castilloConfig,
        'mpd',
        'host',
        '127.0.0.1'
    );


$castilloMpdPort =
    castilloConfigInt(
        $castilloConfig,
        'mpd',
        'port',
        6600
    );


if (
    $castilloMpdPort < 1 ||
    $castilloMpdPort > 65535
) {
    throw new RuntimeException(
        'El puerto MPD debe estar entre 1 y 65535.'
    );
}


$castilloMoodeWwwRoot =
    castilloNormalizeAbsolutePath(
        castilloConfigString(
            $castilloConfig,
            'moode',
            'www_root',
            '/var/www'
        ),
        'www_root'
    );


$castilloBtAudioScript =
    castilloNormalizeAbsolutePath(
        castilloConfigString(
            $castilloConfig,
            'moode',
            'btaudio_script',
            '/var/www/util/set-btaudio.php'
        ),
        'btaudio_script'
    );


$castilloNasEnabled =
    castilloConfigBool(
        $castilloConfig,
        'nas',
        'enabled',
        false
    );


$castilloNasReadonlyRoot =
    castilloNormalizeOptionalPath(
        castilloConfigString(
            $castilloConfig,
            'nas',
            'readonly_root',
            '',
            true
        ),
        'nas.readonly_root'
    );


$castilloNasReadwriteRoot =
    castilloNormalizeOptionalPath(
        castilloConfigString(
            $castilloConfig,
            'nas',
            'readwrite_root',
            '',
            true
        ),
        'nas.readwrite_root'
    );


if ($castilloNasEnabled) {
    if (
        $castilloNasReadonlyRoot === '' ||
        $castilloNasReadwriteRoot === ''
    ) {
        throw new RuntimeException(
            'NAS está habilitado pero faltan sus rutas.'
        );
    }
}


/*
 * ============================================================
 * Public constants
 * ============================================================
 */

define(
    'MPD_HOST',
    $castilloMpdHost
);

define(
    'MPD_PORT',
    $castilloMpdPort
);

define(
    'CASTILLO_INSTALL_DIR',
    $castilloInstallDir
);


define(
    'CASTILLO_BACKEND_DIR',
    CASTILLO_INSTALL_DIR .
    '/backend'
);


define(
    'CASTILLO_WAVEFORM_SCRIPT',
    CASTILLO_BACKEND_DIR .
    '/waveform.py'
);

/*
 * Nombres nuevos.
 */

define(
    'CASTILLO_LIBRARY_ROOT',
    $castilloLibraryRoot
);

define(
    'CASTILLO_LIBRARY_MPD_PREFIX',
    $castilloLibraryMpdPrefix
);

define(
    'CASTILLO_STATE_DIR',
    $castilloStateDir
);

define(
    'CASTILLO_HELPER_DIR',
    $castilloHelperDir
);


/*
 * Runtime.
 */

define(
    'CASTILLO_STATE',
    CASTILLO_STATE_DIR .
    '/state.json'
);

define(
    'CASTILLO_LIBRARY_CACHE',
    CASTILLO_STATE_DIR .
    '/library-cache.json'
);

define(
    'CASTILLO_HISTORY',
    CASTILLO_STATE_DIR .
    '/history.json'
);

define(
    'CASTILLO_HASHTAGS_DB',
    CASTILLO_STATE_DIR .
    '/hashtags.sqlite'
);

define(
    'CASTILLO_PENDING_CHANGES',
    CASTILLO_STATE_DIR .
    '/pending-changes.json'
);

define(
    'CASTILLO_WAVEFORM_DIR',
    CASTILLO_STATE_DIR .
    '/waveforms'
);


/*
 * Helpers.
 */

define(
    'CASTILLO_AUDIO_OUTPUT_HELPER',
    CASTILLO_HELPER_DIR .
    '/castillo-audio-output'
);

define(
    'CASTILLO_COVER_HELPER',
    CASTILLO_HELPER_DIR .
    '/castillo-cover-save'
);

define(
    'CASTILLO_HASHTAGS_HELPER',
    CASTILLO_HELPER_DIR .
    '/castillo-hashtags-index'
);

define(
    'CASTILLO_LRC_HELPER',
    CASTILLO_HELPER_DIR .
    '/castillo-lrc-save'
);

define(
    'CASTILLO_TAGS_HELPER',
    CASTILLO_HELPER_DIR .
    '/castillo-tags-save'
);


/*
 * moOde.
 */

define(
    'MOODE_WWW_ROOT',
    $castilloMoodeWwwRoot
);

define(
    'MOODE_BTAUDIO_SCRIPT',
    $castilloBtAudioScript
);


/*
 * NAS opcional.
 */

define(
    'CASTILLO_NAS_ENABLED',
    $castilloNasEnabled
);

define(
    'CASTILLO_NAS_READONLY_ROOT',
    $castilloNasReadonlyRoot
);

define(
    'CASTILLO_NAS_READWRITE_ROOT',
    $castilloNasReadwriteRoot
);


/*
 * Evitamos dejar la configuración completa
 * como variable global del request.
 */

unset(
    $castilloConfig,
    $castilloInstallDir,
    $castilloLibraryRoot,
    $castilloLibraryMpdPrefix,
    $castilloStateDir,
    $castilloHelperDir,
    $castilloMpdHost,
    $castilloMpdPort,
    $castilloMoodeWwwRoot,
    $castilloBtAudioScript,
    $castilloNasEnabled,
    $castilloNasReadonlyRoot,
    $castilloNasReadwriteRoot
);