<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';


function hashtagDb(): SQLite3
{
    if (!is_file(
        CASTILLO_HASHTAGS_DB
    )) {
        jsonResponse([
            'error' =>
                'El índice de hashtags todavía no existe.'
        ], 503);
    }


    try {
        $db =
            new SQLite3(
                CASTILLO_HASHTAGS_DB,
                SQLITE3_OPEN_READONLY
            );

        $db->busyTimeout(
            3000
        );

        return $db;

    } catch (Throwable $error) {
        jsonResponse([
            'error' =>
                'No fue posible abrir el índice de hashtags.'
        ], 500);
    }
}

function fetchScalar(
    SQLite3 $db,
    string $sql
): int {
    $result =
        $db->querySingle(
            $sql
        );


    return
        is_numeric($result)
            ? (int) $result
            : 0;
}


function hashtagStats(
    SQLite3 $db
): array {
    $generatedAt =
        $db->querySingle(
            "
            SELECT value
            FROM meta
            WHERE key = 'generated_at'
            "
        );


    return [
        'songs_indexed' =>
            fetchScalar(
                $db,
                'SELECT COUNT(*) FROM songs'
            ),

        'songs_with_hashtags' =>
            fetchScalar(
                $db,
                '
                SELECT COUNT(DISTINCT file)
                FROM song_hashtags
                '
            ),

        'hashtags' =>
            fetchScalar(
                $db,
                'SELECT COUNT(*) FROM hashtags'
            ),

        'links' =>
            fetchScalar(
                $db,
                'SELECT COUNT(*) FROM song_hashtags'
            ),

        'embedded_links' =>
            fetchScalar(
                $db,
                "
                SELECT COUNT(*)
                FROM song_hashtags
                WHERE source = 'embedded'
                "
            ),

        'legacy_links' =>
            fetchScalar(
                $db,
                "
                SELECT COUNT(*)
                FROM song_hashtags
                WHERE source = 'legacy'
                "
            ),

        'generated_at' =>
            is_string(
                $generatedAt
            )
                ? $generatedAt
                : null
    ];
}


function listHashtags(
    SQLite3 $db
): array {
    $sql = "
        SELECT
            h.name,

            COUNT(
                DISTINCT sh.file
            ) AS song_count,

            SUM(
                CASE
                    WHEN sh.source = 'embedded'
                    THEN 1
                    ELSE 0
                END
            ) AS embedded_count,

            SUM(
                CASE
                    WHEN sh.source = 'legacy'
                    THEN 1
                    ELSE 0
                END
            ) AS legacy_count

        FROM hashtags h

        JOIN song_hashtags sh
          ON sh.hashtag = h.name

        GROUP BY h.name

        ORDER BY
            song_count DESC,
            h.name COLLATE NOCASE ASC
    ";


    $result =
        $db->query(
            $sql
        );


    if ($result === false) {
        jsonResponse([
            'error' =>
                'No fue posible leer los hashtags.'
        ], 500);
    }


    $items = [];


    while (
        $row =
            $result->fetchArray(
                SQLITE3_ASSOC
            )
    ) {
        $items[] = [
            'name' =>
                (string) (
                    $row['name']
                    ?? ''
                ),

            'count' =>
                (int) (
                    $row['song_count']
                    ?? 0
                ),

            'embedded_count' =>
                (int) (
                    $row['embedded_count']
                    ?? 0
                ),

            'legacy_count' =>
                (int) (
                    $row['legacy_count']
                    ?? 0
                )
        ];
    }


    return $items;
}


function songsForHashtag(
    SQLite3 $db,
    string $hashtag
): array {
    $statement =
        $db->prepare(
            "
            SELECT
                s.file,
                s.title,
                s.artist,
                s.album,
                s.albumartist,
                s.size,
                sh.source

            FROM song_hashtags sh

            JOIN songs s
              ON s.file = sh.file

            WHERE sh.hashtag = :hashtag

            ORDER BY
                CASE
                    WHEN s.artist = ''
                    THEN 1
                    ELSE 0
                END,

                s.artist COLLATE NOCASE ASC,

                CASE
                    WHEN s.album = ''
                    THEN 1
                    ELSE 0
                END,

                s.album COLLATE NOCASE ASC,

                s.title COLLATE NOCASE ASC,

                s.file COLLATE NOCASE ASC
            "
        );


    if ($statement === false) {
        jsonResponse([
            'error' =>
                'No fue posible preparar la consulta.'
        ], 500);
    }


    $statement->bindValue(
        ':hashtag',
        $hashtag,
        SQLITE3_TEXT
    );


    $result =
        $statement->execute();


    if ($result === false) {
        jsonResponse([
            'error' =>
                'No fue posible leer las canciones.'
        ], 500);
    }


    $songs = [];


    while (
        $row =
            $result->fetchArray(
                SQLITE3_ASSOC
            )
    ) {
        $songs[] = [
            'file' =>
                (string) (
                    $row['file']
                    ?? ''
                ),

            'title' =>
                (string) (
                    $row['title']
                    ?? ''
                ),

            'artist' =>
                (string) (
                    $row['artist']
                    ?? ''
                ),

            'album' =>
                (string) (
                    $row['album']
                    ?? ''
                ),

            'albumartist' =>
                (string) (
                    $row['albumartist']
                    ?? ''
                ),

            'size' =>
                (int) (
                    $row['size']
                    ?? 0
                ),

            'hashtag' =>
                $hashtag,

            'hashtag_source' =>
                (string) (
                    $row['source']
                    ?? 'embedded'
                )
        ];
    }


    return $songs;
}


function hashtagsForFile(
    SQLite3 $db,
    string $file
): array {
    $statement =
        $db->prepare(
            "
            SELECT
                hashtag,
                source

            FROM song_hashtags

            WHERE file = :file

            ORDER BY
                hashtag COLLATE NOCASE ASC
            "
        );


    if ($statement === false) {
        jsonResponse([
            'error' =>
                'No fue posible preparar la consulta.'
        ], 500);
    }


    $statement->bindValue(
        ':file',
        $file,
        SQLITE3_TEXT
    );


    $result =
        $statement->execute();


    if ($result === false) {
        jsonResponse([
            'error' =>
                'No fue posible leer los hashtags de la canción.'
        ], 500);
    }


    $items = [];


    while (
        $row =
            $result->fetchArray(
                SQLITE3_ASSOC
            )
    ) {
        $items[] = [
            'name' =>
                (string) (
                    $row['hashtag']
                    ?? ''
                ),

            'source' =>
                (string) (
                    $row['source']
                    ?? 'embedded'
                )
        ];
    }


    return $items;
}


$method =
    $_SERVER['REQUEST_METHOD']
    ?? 'GET';


if ($method !== 'GET') {
    jsonResponse([
        'error' =>
            'Método no permitido.'
    ], 405);
}


$db =
    hashtagDb();


$file =
    trim(
        (string) (
            $_GET['file']
            ?? ''
        )
    );


$hashtag =
    trim(
        (string) (
            $_GET['hashtag']
            ?? ''
        )
    );


if ($file !== '') {
    jsonResponse([
        'ok' => true,

        'file' =>
            $file,

        'hashtags' =>
            hashtagsForFile(
                $db,
                $file
            )
    ]);
}


if ($hashtag !== '') {
    $hashtag =
        ltrim(
            $hashtag,
            '#'
        );


    $songs =
        songsForHashtag(
            $db,
            $hashtag
        );


    jsonResponse([
        'ok' => true,

        'hashtag' =>
            $hashtag,

        'count' =>
            count(
                $songs
            ),

        'songs' =>
            $songs
    ]);
}


$hashtags =
    listHashtags(
        $db
    );


jsonResponse([
    'ok' => true,

    'stats' =>
        hashtagStats(
            $db
        ),

    'count' =>
        count(
            $hashtags
        ),

    'hashtags' =>
        $hashtags
]);
