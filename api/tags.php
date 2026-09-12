<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';


/*
 * ============================================================
 * Optional NAS / pending integration
 * ============================================================
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


function requestMpdUpdate(
    string $file
): void {
    $command = [
        '/usr/bin/mpc',
        'update',
        $file
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
        return;
    }


    fclose(
        $pipes[0]
    );


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


    proc_close(
        $process
    );
}


function invalidateCastilloLibraryCache(): void
{
    $cache =
        CASTILLO_LIBRARY_CACHE;


    if (!is_file($cache)) {
        return;
    }


    if (!@unlink($cache)) {
        error_log(
            '[Castillo tags] No fue posible eliminar cache: ' .
            $cache
        );

        return;
    }


    clearstatcache(
        true,
        $cache
    );
}


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
                'No se encontró el archivo.'
        ], 404);
    }


    /*
     * realpath() resuelve ../ y enlaces simbólicos.
     * El archivo final debe permanecer dentro
     * de library_root.
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

function readTagsAndDetails(
    string $audio,
    string $logicalFile
): array {
    $script = <<<'PY'
import json
import os
import subprocess
import sys

from mutagen import File
from mutagen.easyid3 import EasyID3
from mutagen.easymp4 import EasyMP4Tags
from mutagen.id3 import COMM, ID3
from mutagen.mp4 import MP4Tags


path = sys.argv[1]
logical_file = sys.argv[2]


def register_easy_tag_extensions():
    def comment_get(id3, key):
        values = []

        for frame in id3.getall("COMM"):
            for text in getattr(frame, "text", []) or []:
                value = str(text).strip()

                if value:
                    values.append(value)

        return values

    def comment_set(id3, key, values):
        id3.delall("COMM")

        clean = [
            str(value).strip()
            for value in values
            if str(value).strip()
        ]

        if clean:
            id3.add(
                COMM(
                    encoding=3,
                    lang="eng",
                    desc="",
                    text=clean,
                )
            )

    def comment_delete(id3, key):
        id3.delall("COMM")

    def comment_list(id3, key):
        return ["comment"] if id3.getall("COMM") else []

    EasyID3.RegisterKey(
        "comment",
        getter=comment_get,
        setter=comment_set,
        deleter=comment_delete,
        lister=comment_list,
    )

    EasyID3.RegisterTextKey("composer", "TCOM")
    EasyID3.RegisterTextKey("bpm", "TBPM")
    EasyMP4Tags.RegisterTextKey("composer", "\xa9wrt")


register_easy_tag_extensions()


CASTILLO_HASHTAGS_KEY = "CASTILLO_HASHTAGS"
MP4_HASHTAGS_KEY = "----:com.apple.iTunes:CASTILLO_HASHTAGS"


def split_hashtag_storage(value):
    value = str(
        value or ""
    ).strip()

    if not value:
        return []

    result = []
    seen = set()

    for item in value.split(";"):
        tag = item.strip().lstrip("#")

        if not tag or tag in seen:
            continue

        seen.add(tag)
        result.append(tag)

    return result


def read_castillo_hashtags(raw):
    tags_obj = getattr(
        raw,
        "tags",
        None
    )

    if tags_obj is None:
        return []

    try:
        if isinstance(tags_obj, ID3):
            for frame in tags_obj.getall("TXXX"):
                if (
                    str(getattr(frame, "desc", "")).casefold()
                    == CASTILLO_HASHTAGS_KEY.casefold()
                ):
                    values = getattr(frame, "text", []) or []

                    if values:
                        return split_hashtag_storage(
                            values[0]
                        )

            return []

        if isinstance(tags_obj, MP4Tags):
            values = tags_obj.get(
                MP4_HASHTAGS_KEY,
                []
            )

            if not values:
                return []

            first = values[0]

            if isinstance(first, bytes):
                text = first.decode(
                    "utf-8",
                    errors="replace"
                )
            else:
                text = str(first)

            return split_hashtag_storage(
                text
            )

        for key in tags_obj.keys():
            if (
                str(key).casefold()
                == CASTILLO_HASHTAGS_KEY.casefold()
            ):
                values = tags_obj.get(
                    key,
                    []
                )

                if values:
                    return split_hashtag_storage(
                        values[0]
                    )

    except Exception:
        return []

    return []


try:
    easy = File(
        path,
        easy=True
    )

    raw = File(
        path,
        easy=False
    )

except Exception as error:
    print(
        json.dumps({
            "ok": False,
            "error": str(error)
        })
    )

    sys.exit(1)


if easy is None or raw is None:
    print(
        json.dumps({
            "ok": False,
            "error": "Formato no soportado."
        })
    )

    sys.exit(1)


def value(name):
    try:
        values = easy.get(
            name,
            []
        )

        if not values:
            return ""

        return str(
            values[0]
        )

    except Exception:
        return ""


def split_number(raw_value):
    raw_value = str(
        raw_value or ""
    ).strip()

    if not raw_value:
        return "", ""

    if "/" in raw_value:
        number, total = raw_value.split(
            "/",
            1
        )

        return (
            number.strip(),
            total.strip()
        )

    return raw_value, ""


tracknumber, tracktotal = split_number(
    value("tracknumber")
)

discnumber, disctotal = split_number(
    value("discnumber")
)


stat = os.stat(
    path
)

info = getattr(
    raw,
    "info",
    None
)


def int_attr(name):
    try:
        value = getattr(
            info,
            name,
            0
        )

        return int(
            value or 0
        )

    except Exception:
        return 0


def float_attr(name):
    try:
        value = getattr(
            info,
            name,
            0
        )

        return float(
            value or 0
        )

    except Exception:
        return 0.0


def probe_mp3_packets(audio_path):
    """
    Obtiene duración y bitrate medio a partir de los paquetes MPEG reales.

    Esto evita las estimaciones incorrectas de Mutagen/MPD en archivos MP3
    VBR que no contienen una cabecera Xing/Info/VBRI válida.
    """
    try:
        process = subprocess.run(
            [
                "/usr/bin/ffprobe",
                "-v",
                "error",
                "-select_streams",
                "a:0",
                "-show_packets",
                "-show_entries",
                "packet=duration_time,size",
                "-of",
                "json",
                audio_path,
            ],
            capture_output=True,
            text=True,
            timeout=30,
            check=False,
        )

        if process.returncode != 0:
            return 0.0, 0

        payload = json.loads(
            process.stdout or "{}"
        )

        duration = 0.0
        compressed_bytes = 0

        for packet in payload.get("packets", []):
            try:
                duration += float(
                    packet.get("duration_time", 0) or 0
                )
            except Exception:
                pass

            try:
                compressed_bytes += int(
                    packet.get("size", 0) or 0
                )
            except Exception:
                pass

        bitrate = 0

        if duration > 0 and compressed_bytes > 0:
            bitrate = int(
                round(
                    compressed_bytes * 8 / duration
                )
            )

        return duration, bitrate

    except Exception:
        return 0.0, 0


bits = int_attr(
    "bits_per_sample"
)

if bits <= 0:
    bits = int_attr(
        "bits_per_raw_sample"
    )


extension = os.path.splitext(
    path
)[1].lower().lstrip(".")

format_names = {
    "mp3": "MP3",
    "flac": "FLAC",
    "m4a": "M4A",
    "mp4": "MP4",
    "aac": "AAC",
    "ogg": "OGG",
    "oga": "OGG",
    "opus": "OPUS",
    "wav": "WAV",
    "aif": "AIFF",
    "aiff": "AIFF",
}

format_name = format_names.get(
    extension,
    extension.upper() or "Audio"
)

# Duración/bitrate técnico. Para MP3 usamos la suma de paquetes MPEG
# reales, no la estimación de Mutagen, porque algunos VBR sin Xing/VBRI
# pueden aparecer como archivos de decenas de minutos aunque el audio sea
# mucho más corto.
detail_duration = float_attr("length")
detail_bitrate = int_attr("bitrate")
duration_source = "mutagen"

if extension == "mp3":
    packet_duration, packet_bitrate = probe_mp3_packets(
        path
    )

    if packet_duration > 0:
        detail_duration = packet_duration
        duration_source = "mpeg-packets"

    if packet_bitrate > 0:
        detail_bitrate = packet_bitrate

codec = raw.__class__.__name__

codec_detail = ""

for name in (
    "codec_description",
    "codec",
    "profile"
):
    try:
        candidate = getattr(
            info,
            name,
            ""
        )

        if candidate:
            codec_detail = str(
                candidate
            )
            break
    except Exception:
        pass


lrc_path = os.path.splitext(
    path
)[0] + ".lrc"

lrc_exists = os.path.isfile(
    lrc_path
)


result = {
    "ok": True,

    "tags": {
        "title": value("title"),
        "artist": value("artist"),
        "album": value("album"),
        "albumartist": value("albumartist"),
        "date": value("date"),
        "tracknumber": tracknumber,
        "tracktotal": tracktotal,
        "discnumber": discnumber,
        "disctotal": disctotal,
        "genre": value("genre"),
        "composer": value("composer"),
        "comment": value("comment"),
        "bpm": value("bpm"),
        "hashtags": read_castillo_hashtags(raw),
    },

    "details": {
        "duration_seconds": detail_duration,
        "duration_source": duration_source,
        "bitrate": detail_bitrate,
        "sample_rate": int_attr("sample_rate"),
        "bits_per_sample": bits,
        "channels": int_attr("channels"),
        "format": format_name,
        "codec": codec,
        "codec_detail": codec_detail,
        "size": int(stat.st_size),
        "mtime_epoch": float(stat.st_mtime),
        "mtime_ns": int(stat.st_mtime_ns),
        "logical_file": logical_file,
        "filename": os.path.basename(path),
        "lrc_exists": lrc_exists,
        "lrc_name": (
            os.path.basename(lrc_path)
            if lrc_exists
            else ""
        ),
        "lrc_size": (
            int(os.path.getsize(lrc_path))
            if lrc_exists
            else 0
        ),
        "hashtag_count": len(
            read_castillo_hashtags(raw)
        ),
    }
}


print(
    json.dumps(
        result,
        ensure_ascii=False
    )
)
PY;


    $command = [
        '/usr/bin/python3',
        '-c',
        $script,
        $audio,
        $logicalFile
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
                'No fue posible leer los tags.'
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


    $result =
        json_decode(
            trim($stdout),
            true
        );


    if (
        $status !== 0 ||
        !is_array($result) ||
        empty($result['ok'])
    ) {
        jsonResponse([
            'error' =>
                $result['error']
                ?? trim($stderr)
                ?: 'No fue posible leer los tags.'
        ], 500);
    }


    return $result;
}


$method =
    $_SERVER['REQUEST_METHOD']
    ?? 'GET';


/*
 * =========================
 * GET
 * =========================
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


    $result =
        readTagsAndDetails(
            $audio,
            $file
        );


    jsonResponse([
        'ok' => true,
        'file' => $file,
        'tags' => $result['tags'] ?? [],
        'details' => $result['details'] ?? []
    ]);
}


/*
 * =========================
 * POST
 * =========================
 */
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


    $tags =
        $data['tags']
        ?? null;


    if (!is_array($tags)) {
        jsonResponse([
            'error' =>
                'Tags inválidos.'
        ], 400);
    }


    $audio =
        resolveLocalAudio(
            $file
        );


    if (
        !is_file(
            CASTILLO_TAGS_HELPER
        ) ||
        !is_executable(
            CASTILLO_TAGS_HELPER
        )
    ) {
        jsonResponse([
            'error' =>
                'El helper de edición de tags no está disponible.'
        ], 500);
    }


    $command = [
        '/usr/bin/sudo',
        '-n',

        CASTILLO_TAGS_HELPER,

        '--audio',
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
        jsonResponse([
            'error' =>
                'No fue posible iniciar el guardado.'
        ], 500);
    }


    fwrite(
        $pipes[0],
        json_encode(
            $tags,
            JSON_UNESCAPED_UNICODE
        )
    );

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
                ?: 'No fue posible guardar los tags.'
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
                'Respuesta inválida del guardado.'
        ], 500);
    }


    $hashtagsIndex =
        $result['hashtags_index']
        ?? null;


    /*
     * El guardado del archivo es la operación principal.
     * Si el índice reconstruible falla, no fingimos que
     * el guardado falló: registramos el problema y
     * devolvemos su estado al frontend para diagnóstico.
     */
    if (
        is_array($hashtagsIndex) &&
        empty($hashtagsIndex['ok'])
    ) {
        error_log(
            '[Castillo hashtags index] ' .
            (
                $hashtagsIndex['error']
                ?? 'Actualización no confirmada.'
            )
        );
    }


    /*
     * El archivo ya contiene los tags nuevos.
     * Invalidamos la biblioteca cacheada.
     */
    invalidateCastilloLibraryCache();


    /*
     * Pedimos a MPD que vuelva a inspeccionar
     * el archivo modificado.
     */
    requestMpdUpdate(
        $file
    );


    if (
        CASTILLO_NAS_ENABLED &&
        function_exists(
            'registerPendingChange'
        )
    ) {
        try {
            registerPendingChange(
                $audio,
                'tags',
                $file
            );

        } catch (Throwable $error) {
            error_log(
                '[Castillo pending] Tags: ' .
                $error->getMessage()
            );
        }
    }


    /*
     * Leemos otra vez desde disco para devolver
     * los valores y detalles reales finales.
     */
    $fresh =
        readTagsAndDetails(
            $audio,
            $file
        );


    jsonResponse([
        'ok' => true,
        'file' => $file,
        'tags' => $fresh['tags'] ?? [],
        'details' => $fresh['details'] ?? [],
        'hashtags_index' => $hashtagsIndex
    ]);
}


jsonResponse([
    'error' =>
        'Método no permitido.'
], 405);
