#!/usr/bin/env python3

import argparse
import array
import hashlib
import json
import math
import os
import subprocess
import sys
import tempfile


CACHE_VERSION = "1"
FFMPEG = "/usr/bin/ffmpeg"


def fail(message, code=1):
    print(
        json.dumps(
            {
                "ok": False,
                "error": message
            },
            ensure_ascii=False
        ),
        file=sys.stderr
    )

    sys.exit(code)


parser = argparse.ArgumentParser()


parser.add_argument(
    "--file",
    required=True
)


parser.add_argument(
    "--bars",
    type=int,
    default=220
)


parser.add_argument(
    "--cache-dir",
    required=True
)


args = parser.parse_args()


#
# ============================================================
# Input
# ============================================================
#

audio_file = os.path.realpath(
    args.file
)


cache_dir = os.path.realpath(
    args.cache_dir
)


bars = max(
    60,
    min(
        400,
        args.bars
    )
)


if not os.path.isfile(
    audio_file
):
    fail(
        "No existe el archivo de audio.",
        10
    )


if not os.access(
    audio_file,
    os.R_OK
):
    fail(
        "No hay permisos para leer el archivo.",
        11
    )


#
# ============================================================
# FFmpeg
# ============================================================
#

if not os.path.isfile(
    FFMPEG
):
    fail(
        "No se encontró FFmpeg.",
        12
    )


if not os.access(
    FFMPEG,
    os.X_OK
):
    fail(
        "FFmpeg no es ejecutable.",
        13
    )


#
# ============================================================
# Cache directory
# ============================================================
#

try:
    os.makedirs(
        cache_dir,
        exist_ok=True
    )

except OSError as error:
    fail(
        (
            "No fue posible crear el directorio "
            f"de caché: {error}"
        ),
        14
    )


if not os.path.isdir(
    cache_dir
):
    fail(
        "La ruta de caché no es un directorio.",
        15
    )


if not os.access(
    cache_dir,
    os.W_OK
):
    fail(
        "No hay permisos para escribir en la caché.",
        16
    )


#
# ============================================================
# Audio metadata
# ============================================================
#

try:
    stat = os.stat(
        audio_file
    )

except OSError as error:
    fail(
        f"No fue posible leer el archivo de audio: {error}",
        17
    )


#
# ============================================================
# Cache key
# ============================================================
#
# La clave cambia cuando cambia:
#
# - versión del algoritmo
# - archivo
# - mtime
# - tamaño
# - número de barras
#

cache_source = (
    f"{CACHE_VERSION}\0"
    f"{audio_file}\0"
    f"{stat.st_mtime_ns}\0"
    f"{stat.st_size}\0"
    f"{bars}"
)


cache_name = (
    hashlib.sha256(
        cache_source.encode(
            "utf-8"
        )
    ).hexdigest()
    + ".json"
)


cache_file = os.path.join(
    cache_dir,
    cache_name
)


#
# ============================================================
# Existing cache
# ============================================================
#

if os.path.isfile(
    cache_file
):
    try:
        with open(
            cache_file,
            "r",
            encoding="utf-8"
        ) as handle:
            print(
                handle.read()
            )

        sys.exit(0)

    except OSError:
        #
        # Si la caché está dañada o no puede leerse,
        # simplemente volvemos a generarla.
        #
        pass


#
# ============================================================
# Decode audio
# ============================================================
#
# Decodificamos solamente a:
#
# mono
# 1000 Hz
# PCM 16 bit
#
# No necesitamos calidad de audio;
# solamente la envolvente de amplitud.
#

command = [
    FFMPEG,

    "-v",
    "error",

    "-i",
    audio_file,

    "-map",
    "a:0",

    "-ac",
    "1",

    "-ar",
    "1000",

    "-f",
    "s16le",

    "-acodec",
    "pcm_s16le",

    "pipe:1"
]


try:
    process = subprocess.run(
        command,

        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,

        check=False,

        timeout=180
    )

except subprocess.TimeoutExpired:
    fail(
        "FFmpeg tardó demasiado generando el waveform.",
        20
    )

except OSError as error:
    fail(
        f"No fue posible ejecutar FFmpeg: {error}",
        21
    )


if process.returncode != 0:
    error = process.stderr.decode(
        "utf-8",
        errors="replace"
    ).strip()

    fail(
        error or
        "FFmpeg no pudo analizar el archivo.",
        22
    )


#
# ============================================================
# PCM samples
# ============================================================
#

samples = array.array(
    "h"
)


samples.frombytes(
    process.stdout
)


if sys.byteorder != "little":
    samples.byteswap()


if not samples:
    fail(
        "FFmpeg no devolvió muestras de audio.",
        23
    )


sample_count = len(
    samples
)


raw_values = []


#
# ============================================================
# Waveform blocks
# ============================================================
#
# Dividimos toda la canción exactamente
# en N bloques.
#

for index in range(
    bars
):

    start = (
        index *
        sample_count //
        bars
    )


    end = (
        (index + 1) *
        sample_count //
        bars
    )


    if end <= start:
        raw_values.append(
            0.0
        )

        continue


    total = 0
    peak = 0

    count = (
        end - start
    )


    for sample in samples[
        start:end
    ]:

        value = abs(
            int(sample)
        )


        total += value


        if value > peak:
            peak = value


    average = (
        total / count
        if count
        else 0
    )


    #
    # Combinamos promedio + pico.
    #

    value = (
        average * 0.72 +
        peak * 0.28
    )


    raw_values.append(
        value
    )


#
# ============================================================
# Normalization
# ============================================================
#
# Usamos aproximadamente el percentil 95
# para que un único pico enorme no haga
# diminuto el resto del waveform.
#

positive = sorted(
    value
    for value in raw_values
    if value > 0
)


if positive:

    index95 = min(
        len(positive) - 1,

        int(
            len(positive) *
            0.95
        )
    )


    normalization = max(
        positive[index95],
        1
    )

else:
    normalization = 1


waveform = []


for value in raw_values:

    normalized = min(
        1.0,
        value / normalization
    )


    #
    # Elevar ligeramente zonas silenciosas
    # para obtener una representación
    # visual más legible.
    #

    if normalized > 0:

        normalized = math.pow(
            normalized,
            0.72
        )


        normalized = max(
            0.055,
            normalized
        )


    waveform.append(
        round(
            normalized,
            4
        )
    )


#
# ============================================================
# Result
# ============================================================
#

result = {
    "ok": True,

    "bars": waveform,

    "count": len(
        waveform
    ),

    "size": stat.st_size,

    "mtime": int(
        stat.st_mtime
    )
}


json_data = json.dumps(
    result,
    ensure_ascii=False,
    separators=(",", ":")
)


#
# ============================================================
# Atomic cache write
# ============================================================
#

try:
    fd, temporary = tempfile.mkstemp(
        prefix=".waveform-",
        suffix=".tmp",
        dir=cache_dir
    )

except OSError as error:
    fail(
        f"No fue posible crear la caché temporal: {error}",
        24
    )


try:

    with os.fdopen(
        fd,
        "w",
        encoding="utf-8"
    ) as handle:

        handle.write(
            json_data
        )


        handle.flush()


        os.fsync(
            handle.fileno()
        )


    os.replace(
        temporary,
        cache_file
    )


except OSError as error:
    fail(
        f"No fue posible guardar la caché: {error}",
        25
    )


finally:

    if os.path.exists(
        temporary
    ):
        try:
            os.unlink(
                temporary
            )

        except OSError:
            pass


print(
    json_data
)