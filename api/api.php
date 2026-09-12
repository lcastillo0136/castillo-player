<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';

/*
 * NORMALIZACIÓN PARA BÚSQUEDA
 *
 * - ignora mayúsculas/minúsculas
 * - elimina acentos y diacríticos
 * - ignora puntuación
 * - conserva caracteres Unicode
 * - permite comparar AC/DC con ACDC
 */
function normalizeSearchText(
    mixed $value
): string {
    /*
     * Algunos campos de MPD pueden
     * llegar como arrays.
     */
    if (is_array($value)) {
        $parts = [];

        array_walk_recursive(
            $value,
            function ($item) use (&$parts): void {
                if (
                    is_string($item) ||
                    is_numeric($item)
                ) {
                    $parts[] =
                        (string) $item;
                }
            }
        );

        $value =
            implode(
                ' ',
                $parts
            );
    }


    $text =
        trim(
            (string) $value
        );


    if ($text === '') {
        return '';
    }


    /*
     * Minúsculas Unicode.
     */
    if (
        function_exists(
            'mb_strtolower'
        )
    ) {
        $text =
            mb_strtolower(
                $text,
                'UTF-8'
            );

    } else {
        $text =
            strtolower(
                $text
            );
    }


    /*
     * Descomponer caracteres:
     *
     * ä -> a + ¨
     * á -> a + ´
     *
     * Después eliminamos las marcas.
     *
     * Esto utiliza intl si está
     * disponible, pero existe un
     * fallback más abajo.
     */
    if (
        class_exists(
            'Normalizer'
        )
    ) {
        $normalized =
            Normalizer::normalize(
                $text,
                Normalizer::FORM_D
            );


        if (
            is_string(
                $normalized
            )
        ) {
            $text =
                $normalized;
        }


        $withoutMarks =
            preg_replace(
                '/\p{Mn}+/u',
                '',
                $text
            );


        if (
            is_string(
                $withoutMarks
            )
        ) {
            $text =
                $withoutMarks;
        }
    }


    /*
     * Fallback y caracteres latinos
     * que no siempre se descomponen.
     */
    $text =
        strtr(
            $text,
            [
                'á' => 'a',
                'à' => 'a',
                'â' => 'a',
                'ä' => 'a',
                'ã' => 'a',
                'å' => 'a',
                'ā' => 'a',
                'ă' => 'a',
                'ą' => 'a',
                'æ' => 'ae',

                'ç' => 'c',
                'ć' => 'c',
                'č' => 'c',

                'ď' => 'd',
                'đ' => 'd',
                'ð' => 'd',

                'é' => 'e',
                'è' => 'e',
                'ê' => 'e',
                'ë' => 'e',
                'ē' => 'e',
                'ė' => 'e',
                'ę' => 'e',

                'í' => 'i',
                'ì' => 'i',
                'î' => 'i',
                'ï' => 'i',
                'ī' => 'i',
                'į' => 'i',

                'ł' => 'l',

                'ñ' => 'n',
                'ń' => 'n',
                'ň' => 'n',

                'ó' => 'o',
                'ò' => 'o',
                'ô' => 'o',
                'ö' => 'o',
                'õ' => 'o',
                'ø' => 'o',
                'ō' => 'o',
                'ő' => 'o',
                'œ' => 'oe',

                'ř' => 'r',

                'ś' => 's',
                'š' => 's',
                'ş' => 's',
                'ß' => 'ss',

                'ť' => 't',
                'þ' => 'th',

                'ú' => 'u',
                'ù' => 'u',
                'û' => 'u',
                'ü' => 'u',
                'ū' => 'u',
                'ů' => 'u',
                'ű' => 'u',

                'ý' => 'y',
                'ÿ' => 'y',

                'ž' => 'z',
                'ź' => 'z',
                'ż' => 'z'
            ]
        );


    /*
     * Todo lo que no sea letra o número
     * se convierte en espacio.
     *
     * AC/DC -> ac dc
     * AC-DC -> ac dc
     * Guns N' Roses -> guns n roses
     */
    $clean =
        preg_replace(
            '/[^\p{L}\p{N}]+/u',
            ' ',
            $text
        );


    if (
        is_string(
            $clean
        )
    ) {
        $text =
            $clean;
    }


    /*
     * Colapsar espacios.
     */
    $clean =
        preg_replace(
            '/\s+/u',
            ' ',
            $text
        );


    if (
        is_string(
            $clean
        )
    ) {
        $text =
            $clean;
    }


    return trim(
        $text
    );
}


/*
 * Variante sin espacios.
 *
 * AC/DC -> acdc
 * AC DC  -> acdc
 * ACDC   -> acdc
 */
function compactSearchText(
    string $text
): string {
    return str_replace(
        ' ',
        '',
        $text
    );
}

try {
    $data = requestData();

    $action =
        (string) ($data['action'] ?? 'status');


    /*
     * ESTADO DEL PLAYER
     */
    if ($action === 'status') {
        jsonResponse([
            'status' => mpdObject('status'),
            'song' => mpdObject('currentsong')
        ]);
    }


    /*
     * COLA
     */
    if ($action === 'queue') {
        jsonResponse([
            'songs' => mpdSongs('playlistinfo')
        ]);
    }

    /*
     * REPRODUCIR ELEMENTO DE LA COLA
     */
    if ($action === 'queue-play') {
        $id = $data['id'] ?? null;

        if (
            !is_numeric($id) ||
            (int) $id < 0
        ) {
            jsonResponse([
                'error' => 'ID de cola inválido.'
            ], 400);
        }

        mpdRaw(
            'playid ' . (int) $id
        );

        jsonResponse([
            'ok' => true
        ]);
    }


    /*
     * ELIMINAR ELEMENTO DE LA COLA
     */
    if ($action === 'queue-remove') {
        $id = $data['id'] ?? null;

        if (
            !is_numeric($id) ||
            (int) $id < 0
        ) {
            jsonResponse([
                'error' => 'ID de cola inválido.'
            ], 400);
        }

        mpdRaw(
            'deleteid ' . (int) $id
        );

        jsonResponse([
            'ok' => true
        ]);
    }


    /*
     * MOVER ELEMENTO DE LA COLA
     */
    if ($action === 'queue-move') {
        $id = $data['id'] ?? null;
        $position = $data['position'] ?? null;

        if (
            !is_numeric($id) ||
            !is_numeric($position)
        ) {
            jsonResponse([
                'error' =>
                    'Movimiento de cola inválido.'
            ], 400);
        }

        $id = (int) $id;
        $position = max(
            0,
            (int) $position
        );

        mpdRaw(
            'moveid ' .
            $id .
            ' ' .
            $position
        );

        jsonResponse([
            'ok' => true
        ]);
    }


    /*
     * BIBLIOTECA COMPLETA
     */
    if ($action === 'library') {
        $songs = getLibrary();

        $artists = [];
        $albums = [];

        foreach ($songs as $song) {
            $artist =
                trim($song['artist'] ?? '');

            $album =
                trim($song['album'] ?? '');

            if ($artist !== '') {
                $artists[$artist] =
                    ($artists[$artist] ?? 0) + 1;
            }

            if ($album !== '') {
                $key =
                    ($artist ?: '—') .
                    "\x1f" .
                    $album;

                if (!isset($albums[$key])) {
                    $albums[$key] = [
                        'album' => $album,
                        'artist' => $artist,
                        'count' => 0,
                        'file' =>
                            $song['file'] ?? ''
                    ];
                }

                $albums[$key]['count']++;
            }
        }

        ksort(
            $artists,
            SORT_NATURAL |
            SORT_FLAG_CASE
        );

        jsonResponse([
            'songs' => $songs,

            'artists' => array_map(
                fn($name, $count) => [
                    'artist' => $name,
                    'count' => $count
                ],
                array_keys($artists),
                array_values($artists)
            ),

            'albums' => array_values(
                $albums
            ),

            'total' => count($songs)
        ]);
    }

    /*
     * BÚSQUEDA NORMALIZADA
     *
     * Busca sobre:
     *
     * - título
     * - artista
     * - álbum
     * - artista del álbum
     * - compositor
     * - género
     * - ruta física/lógica
     *
     * Ignora:
     *
     * - mayúsculas/minúsculas
     * - acentos
     * - diacríticos
     * - puntuación
     * - diferencias entre separadores
     */
    if ($action === 'search') {
        $query =
            trim(
                (string) (
                    $data['q']
                    ?? ''
                )
            );


        if ($query === '') {
            jsonResponse([
                'songs' => [],
                'total' => 0
            ]);
        }


        $normalizedQuery =
            normalizeSearchText(
                $query
            );


        if (
            $normalizedQuery === ''
        ) {
            jsonResponse([
                'songs' => [],
                'total' => 0
            ]);
        }


        $compactQuery =
            compactSearchText(
                $normalizedQuery
            );


        /*
         * Palabras individuales.
         *
         * "mago viento"
         *
         * podrá coincidir aunque "mago"
         * esté en Artist y "viento"
         * esté en Title.
         */
        $queryTokens =
            array_values(
                array_filter(
                    explode(
                        ' ',
                        $normalizedQuery
                    ),
                    static fn (
                        string $token
                    ): bool =>
                        $token !== ''
                )
            );


        $songs =
            getLibrary();


        $results =
            array_values(
                array_filter(
                    $songs,

                    function (
                        array $song
                    ) use (
                        $normalizedQuery,
                        $compactQuery,
                        $queryTokens
                    ): bool {
                        $fields = [
                            $song['title']
                                ?? '',

                            $song['artist']
                                ?? '',

                            $song['album']
                                ?? '',

                            $song['albumartist']
                                ?? '',

                            $song['composer']
                                ?? '',

                            $song['genre']
                                ?? '',

                            $song['file']
                                ?? ''
                        ];


                        /*
                         * Unimos los campos antes de
                         * normalizar para hacer una sola
                         * normalización por canción.
                         */
                        $searchText =
                            normalizeSearchText(
                                $fields
                            );


                        if (
                            $searchText === ''
                        ) {
                            return false;
                        }


                        /*
                         * Coincidencia normal.
                         *
                         * Mägo de Oz
                         * query: mago
                         */
                        if (
                            str_contains(
                                $searchText,
                                $normalizedQuery
                            )
                        ) {
                            return true;
                        }


                        /*
                         * Coincidencia compacta.
                         *
                         * AC/DC
                         * query: acdc
                         */
                        $compactText =
                            compactSearchText(
                                $searchText
                            );


                        if (
                            $compactQuery !== ''
                            &&
                            str_contains(
                                $compactText,
                                $compactQuery
                            )
                        ) {
                            return true;
                        }


                        /*
                         * Coincidencia por palabras.
                         *
                         * query:
                         * mago viento
                         *
                         * Artist:
                         * Mägo de Oz
                         *
                         * Title:
                         * Molinos de Viento
                         */
                        foreach (
                            $queryTokens
                            as $token
                        ) {
                            if (
                                !str_contains(
                                    $searchText,
                                    $token
                                )
                            ) {
                                return false;
                            }
                        }


                        return
                            count(
                                $queryTokens
                            ) > 1;
                    }
                )
            );


        jsonResponse([
            'songs' =>
                array_slice(
                    $results,
                    0,
                    250
                ),

            'total' =>
                count(
                    $results
                )
        ]);
    }


    /*
     * CONTROL PLAYER
     */
    if ($action === 'control') {
        $command =
            (string) ($data['command'] ?? '');

        switch ($command) {
            case 'play':
                mpdRaw('play');
                break;

            case 'pause':
                mpdRaw('pause 1');
                break;

            case 'resume':
                mpdRaw('pause 0');
                break;

            case 'toggle':
                $status =
                    mpdObject('status');

                if (
                    ($status['state'] ?? '')
                    === 'play'
                ) {
                    mpdRaw('pause 1');
                } else {
                    mpdRaw('pause 0');
                }

                break;

            case 'next':
                mpdRaw('next');
                break;

            case 'previous':
                mpdRaw('previous');
                break;

            case 'clear':
                mpdRaw('clear');
                break;

            case 'volume':
                $value = max(
                    0,
                    min(
                        100,
                        (int) ($data['value'] ?? 0)
                    )
                );

                mpdRaw(
                    'setvol ' . $value
                );

                break;

            case 'seek':
                $seconds = max(
                    0,
                    (float) (
                        $data['value'] ?? 0
                    )
                );

                mpdRaw(
                    'seekcur ' .
                    number_format(
                        $seconds,
                        3,
                        '.',
                        ''
                    )
                );

                break;

            case 'random':
                $enabled =
                    !empty($data['enabled'])
                        ? 1
                        : 0;

                mpdRaw(
                    'random ' . $enabled
                );

                break;

            case 'repeat':
                $enabled =
                    !empty($data['enabled'])
                        ? 1
                        : 0;

                mpdRaw(
                    'repeat ' . $enabled
                );

                break;

            default:
                jsonResponse([
                    'error' =>
                        'Comando no permitido.'
                ], 400);
        }

        jsonResponse([
            'ok' => true,
            'status' => mpdObject('status'),
            'song' => mpdObject(
                'currentsong'
            )
        ]);
    }


    /*
     * REPRODUCIR CANCIÓN AHORA
     */
    if ($action === 'play-file') {
        $file =
            (string) ($data['file'] ?? '');

        if (!validLocalFile($file)) {
            jsonResponse([
                'error' =>
                    'Archivo no autorizado.'
            ], 403);
        }

        mpdRaw('clear');

        mpdRaw(
            'add ' . mpdEscape($file)
        );

        mpdRaw('play');

        jsonResponse([
            'ok' => true
        ]);
    }

    /*
     * REPRODUCIR VARIOS ARCHIVOS
     * Útil para álbumes, artistas y favoritos.
     */
    if ($action === 'play-files') {
        $files = $data['files'] ?? [];

        if (!is_array($files)) {
            jsonResponse([
                'error' => 'Lista de archivos inválida.'
            ], 400);
        }

        $files = array_values(
            array_filter(
                $files,
                fn($file) =>
                    is_string($file) &&
                    validLocalFile($file)
            )
        );

        if (!$files) {
            jsonResponse([
                'error' => 'No hay archivos válidos.'
            ], 400);
        }

        mpdRaw('clear');

        foreach ($files as $file) {
            mpdRaw(
                'add ' . mpdEscape($file)
            );
        }

        mpdRaw('play');

        jsonResponse([
            'ok' => true,
            'count' => count($files)
        ]);
    }

    /*
     * REPRODUCIR TODA LA BIBLIOTECA LOCAL
     */
    if ($action === 'play-all') {
        mpdRaw('clear');

        mpdRaw(
            'add ' .
            mpdEscape(
                rtrim(CASTILLO_LIBRARY_MPD_PREFIX, '/')
            )
        );

        mpdRaw('play');

        jsonResponse([
            'ok' => true
        ]);
    }


    /*
     * AGREGAR VARIOS ARCHIVOS A LA COLA
     */
    if ($action === 'queue-add-many') {
        $files = $data['files'] ?? [];

        if (!is_array($files)) {
            jsonResponse([
                'error' => 'Lista de archivos inválida.'
            ], 400);
        }

        $count = 0;

        foreach ($files as $file) {
            if (
                is_string($file) &&
                validLocalFile($file)
            ) {
                mpdRaw(
                    'add ' . mpdEscape($file)
                );

                $count++;
            }
        }

        jsonResponse([
            'ok' => true,
            'count' => $count
        ]);
    }

    /*
     * AGREGAR A COLA
     */
    if ($action === 'queue-add') {
        $file =
            (string) ($data['file'] ?? '');

        if (!validLocalFile($file)) {
            jsonResponse([
                'error' =>
                    'Archivo no autorizado.'
            ], 403);
        }

        mpdRaw(
            'add ' . mpdEscape($file)
        );

        jsonResponse([
            'ok' => true
        ]);
    }


    /*
     * FAVORITOS
     */
    if ($action === 'favorites') {
        $state =
            loadCastilloState();

        jsonResponse([
            'files' =>
                array_values(
                    $state['favorites']
                )
        ]);
    }


    if ($action === 'favorite-toggle') {
        $file =
            (string) ($data['file'] ?? '');

        if (!validLocalFile($file)) {
            jsonResponse([
                'error' =>
                    'Archivo no autorizado.'
            ], 403);
        }

        $state =
            loadCastilloState();

        $favorites =
            array_values(
                $state['favorites']
            );

        $index = array_search(
            $file,
            $favorites,
            true
        );

        if ($index === false) {
            $favorites[] = $file;
            $favorite = true;
        } else {
            array_splice(
                $favorites,
                $index,
                1
            );

            $favorite = false;
        }

        $state['favorites'] =
            $favorites;

        saveCastilloState($state);

        jsonResponse([
            'ok' => true,
            'favorite' => $favorite
        ]);
    }


    /*
     * PLAYLISTS
     */
    if ($action === 'playlists') {
        $state =
            loadCastilloState();

        jsonResponse([
            'playlists' =>
                $state['playlists']
        ]);
    }


    if ($action === 'playlist-create') {
        $name =
            trim(
                (string) (
                    $data['name'] ?? ''
                )
            );

        if (
            $name === '' ||
            strlen($name) > 240
        ) {
            jsonResponse([
                'error' =>
                    'Nombre de playlist inválido.'
            ], 400);
        }

        $state =
            loadCastilloState();

        if (
            !isset(
                $state['playlists'][$name]
            )
        ) {
            $state['playlists'][$name] = [];
        }

        saveCastilloState($state);

        jsonResponse([
            'ok' => true
        ]);
    }


    if ($action === 'playlist-delete') {
        $name =
            (string) ($data['name'] ?? '');

        $state =
            loadCastilloState();

        unset(
            $state['playlists'][$name]
        );

        saveCastilloState($state);

        jsonResponse([
            'ok' => true
        ]);
    }


    if ($action === 'playlist-add') {
        $name =
            (string) ($data['name'] ?? '');

        $file =
            (string) ($data['file'] ?? '');

        if (!validLocalFile($file)) {
            jsonResponse([
                'error' =>
                    'Archivo no autorizado.'
            ], 403);
        }

        $state =
            loadCastilloState();

        if (
            !isset(
                $state['playlists'][$name]
            )
        ) {
            jsonResponse([
                'error' =>
                    'Playlist inexistente.'
            ], 404);
        }

        if (
            !in_array(
                $file,
                $state['playlists'][$name],
                true
            )
        ) {
            $state['playlists'][$name][] =
                $file;
        }

        saveCastilloState($state);

        jsonResponse([
            'ok' => true
        ]);
    }


    if ($action === 'playlist-remove') {
        $name =
            (string) ($data['name'] ?? '');

        $file =
            (string) ($data['file'] ?? '');

        $state =
            loadCastilloState();

        if (
            isset(
                $state['playlists'][$name]
            )
        ) {
            $state['playlists'][$name] =
                array_values(
                    array_filter(
                        $state['playlists'][$name],
                        fn($item) =>
                            $item !== $file
                    )
                );
        }

        saveCastilloState($state);

        jsonResponse([
            'ok' => true
        ]);
    }


    if ($action === 'playlist-play') {
        $name =
            (string) ($data['name'] ?? '');

        $state =
            loadCastilloState();

        $files =
            $state['playlists'][$name]
            ?? null;

        if (!is_array($files)) {
            jsonResponse([
                'error' =>
                    'Playlist inexistente.'
            ], 404);
        }

        mpdRaw('clear');

        foreach ($files as $file) {
            if (validLocalFile($file)) {
                mpdRaw(
                    'add ' .
                    mpdEscape($file)
                );
            }
        }

        if (count($files) > 0) {
            mpdRaw('play');
        }

        jsonResponse([
            'ok' => true
        ]);
    }


    jsonResponse([
        'error' => 'Acción desconocida.'
    ], 404);

} catch (Throwable $error) {
    jsonResponse([
        'error' => $error->getMessage()
    ], 500);
}
