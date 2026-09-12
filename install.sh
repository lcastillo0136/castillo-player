#!/usr/bin/env bash

set -Eeuo pipefail

###############################################################################
# Castillo Player
# Core installer
#
# This installer:
#   - validates a moOde environment
#   - installs core dependencies when needed
#   - builds the Vue frontend
#   - deploys /castillo/ and /castillo-api/
#   - installs core helpers
#   - installs the core sudoers policy
#   - creates Castillo runtime directories
#   - creates castillo.ini on first installation
#
# It DOES NOT:
#   - install the optional NAS synchronization module
#   - enable NAS synchronization
#   - modify moOde's "/" route
#   - replace an existing castillo.ini
###############################################################################

SCRIPT_DIR="$(
    cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1
    pwd
)"

INSTALL_DIR="/opt/castillo-player"
BACKEND_DIR="${INSTALL_DIR}/backend"

WEB_DIR="/var/www/castillo"
API_DIR="/var/www/castillo-api"

CONFIG_DIR="/etc/castillo-player"
CONFIG_FILE="${CONFIG_DIR}/castillo.ini"

STATE_DIR="/var/lib/castillo-player"
WAVEFORM_DIR="${STATE_DIR}/waveforms"

HELPER_DIR="/usr/local/sbin"

SUDOERS_FILE="/etc/sudoers.d/castillo-player"

CHECK_ONLY=false
NO_APT=false

LIBRARY_ROOT=""
MPD_PREFIX=""

APT_UPDATED=false


###############################################################################
# Output helpers
###############################################################################

info()
{
    printf '\n[INFO] %s\n' "$*"
}


ok()
{
    printf '[ OK ] %s\n' "$*"
}


warn()
{
    printf '[WARN] %s\n' "$*" >&2
}


die()
{
    printf '\n[ERROR] %s\n' "$*" >&2
    exit 1
}


usage()
{
    cat <<'USAGE'
Castillo Player core installer

Usage:
  sudo ./install.sh [options]

Options:
  --library-root PATH
      Physical path to the local music library.
      Required on a new installation unless entered interactively.

  --mpd-prefix PREFIX
      MPD path corresponding to --library-root.
      Example:
          USB/MUSIC_DRIVE/Music/

  --check
      Validate the repository and system without installing anything.

  --no-apt
      Do not install missing Castillo dependencies with apt.

  -h, --help
      Show this help.

Examples:

  sudo ./install.sh \
      --library-root "/media/MUSIC_DRIVE/Music" \
      --mpd-prefix "USB/MUSIC_DRIVE/Music/"

  sudo ./install.sh --check
USAGE
}


###############################################################################
# Cleanup / error reporting
###############################################################################

on_error()
{
    local rc=$?

    printf '\n[ERROR] Installation stopped at line %s (exit %s).\n' \
        "${BASH_LINENO[0]:-unknown}" \
        "$rc" \
        >&2

    exit "$rc"
}

trap on_error ERR


###############################################################################
# Arguments
###############################################################################

while [[ $# -gt 0 ]]; do
    case "$1" in
        --library-root)
            [[ $# -ge 2 ]] ||
                die "--library-root requires a value."

            LIBRARY_ROOT="$2"
            shift 2
            ;;

        --mpd-prefix)
            [[ $# -ge 2 ]] ||
                die "--mpd-prefix requires a value."

            MPD_PREFIX="$2"
            shift 2
            ;;

        --check)
            CHECK_ONLY=true
            shift
            ;;

        --no-apt)
            NO_APT=true
            shift
            ;;

        -h|--help)
            usage
            exit 0
            ;;

        *)
            die "Unknown option: $1"
            ;;
    esac
done


###############################################################################
# Root
###############################################################################

if [[ "${EUID}" -ne 0 ]]; then
    die "Run this installer as root: sudo ./install.sh"
fi


###############################################################################
# Repository validation
###############################################################################

info "Validating Castillo Player source tree"

required_source_files=(
    "${SCRIPT_DIR}/frontend/package.json"
    "${SCRIPT_DIR}/frontend/package-lock.json"
    "${SCRIPT_DIR}/frontend/vite.config.js"
    "${SCRIPT_DIR}/api/config.php"
    "${SCRIPT_DIR}/api/core.php"
    "${SCRIPT_DIR}/api/api.php"
    "${SCRIPT_DIR}/backend/waveform.py"
    "${SCRIPT_DIR}/helpers/castillo-audio-output"
    "${SCRIPT_DIR}/helpers/castillo-cover-save"
    "${SCRIPT_DIR}/helpers/castillo-hashtags-index"
    "${SCRIPT_DIR}/helpers/castillo-lrc-save"
    "${SCRIPT_DIR}/helpers/castillo-tags-save"
    "${SCRIPT_DIR}/config/castillo.ini.example"
    "${SCRIPT_DIR}/config/sudoers-core.template"
)

for path in "${required_source_files[@]}"; do
    [[ -f "$path" ]] ||
        die "Required source file not found: $path"
done

ok "Repository structure is complete"


###############################################################################
# moOde validation
###############################################################################

info "Validating moOde environment"

required_commands=(
    php
    nginx
    mpc
    bluetoothctl
    sudo
    visudo
)

missing_moode_commands=()

for command_name in "${required_commands[@]}"; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        missing_moode_commands+=("$command_name")
    fi
done

if (( ${#missing_moode_commands[@]} > 0 )); then
    printf '%s\n' \
        "Missing commands normally provided by the moOde environment:"

    printf '  - %s\n' "${missing_moode_commands[@]}"

    die "The current system does not look like a compatible moOde installation."
fi


required_moode_files=(
    "/var/www/util/set-btaudio.php"
    "/var/www/inc/audio.php"
    "/var/www/inc/common.php"
    "/var/www/inc/mpd.php"
    "/var/www/inc/session.php"
    "/var/www/inc/sql.php"
)

for path in "${required_moode_files[@]}"; do
    [[ -f "$path" ]] ||
        die "Required moOde file not found: $path"
done


if ! id www-data >/dev/null 2>&1; then
    die "The www-data user does not exist."
fi


if ! php -r '
    exit(class_exists("SQLite3") ? 0 : 1);
'; then
    die "PHP SQLite3 support is required but is not available."
fi


ok "moOde core requirements are available"


###############################################################################
# moOde version detection
###############################################################################

TESTED_MOODE_VERSION="10.3.3"
COMPATIBLE_MOODE_SERIES="10.3"


detect_moode_version()
{
    local system_info
    local line

    system_info="$(
        moodeutl -s 2>/dev/null || true
    )"

    while IFS= read -r line; do

        if [[ "$line" =~ ^[[:space:]]*moOde[[:space:]]+release[[:space:]]*=[[:space:]]*([0-9]+(\.[0-9]+)+) ]]; then

            printf '%s\n' "${BASH_REMATCH[1]}"
            return 0

        fi

    done <<< "$system_info"

    return 0
}


info "Detecting moOde version"

MOODE_VERSION="$(
    detect_moode_version
)"


if [[ -z "$MOODE_VERSION" ]]; then

    warn \
        "The moOde version could not be detected. " \
        "Castillo Player will continue because the required " \
        "moOde interfaces are present."

elif [[ "$MOODE_VERSION" == "$TESTED_MOODE_VERSION" ]]; then

    ok "moOde ${MOODE_VERSION} detected (tested version)"

elif [[ "$MOODE_VERSION" == "${COMPATIBLE_MOODE_SERIES}."* ]]; then

    warn \
        "moOde ${MOODE_VERSION} detected. " \
        "Castillo Player was tested on ${TESTED_MOODE_VERSION}; " \
        "this version belongs to the compatible " \
        "${COMPATIBLE_MOODE_SERIES}.x series. " \
        "Required interfaces are present, so installation may continue."

else

    warn \
        "moOde ${MOODE_VERSION} detected. " \
        "This version is outside the tested " \
        "${COMPATIBLE_MOODE_SERIES}.x series. " \
        "Required interfaces are present, but this exact version " \
        "has not been validated with Castillo Player."
fi


###############################################################################
# Castillo dependencies
###############################################################################

info "Checking Castillo Player dependencies"

missing_packages=()


if ! command -v python3 >/dev/null 2>&1; then
    missing_packages+=("python3")
fi


if command -v python3 >/dev/null 2>&1; then
    if ! python3 -c 'import mutagen' >/dev/null 2>&1; then
        missing_packages+=("python3-mutagen")
    fi
else
    missing_packages+=("python3-mutagen")
fi


if ! command -v ffmpeg >/dev/null 2>&1 ||
   ! command -v ffprobe >/dev/null 2>&1; then
    missing_packages+=("ffmpeg")
fi


if ! command -v node >/dev/null 2>&1; then
    missing_packages+=("nodejs")
fi


if ! command -v npm >/dev/null 2>&1; then
    missing_packages+=("npm")
fi


# Remove duplicate package names.
if (( ${#missing_packages[@]} > 0 )); then
    mapfile -t missing_packages < <(
        printf '%s\n' "${missing_packages[@]}" |
        awk '!seen[$0]++'
    )
fi


if (( ${#missing_packages[@]} > 0 )); then
    printf '\nMissing Castillo dependencies:\n'
    printf '  - %s\n' "${missing_packages[@]}"

    if "$CHECK_ONLY"; then
        warn "Dependencies are missing."
    elif "$NO_APT"; then
        die "Dependencies are missing and --no-apt was specified."
    else
        info "Installing missing dependencies"

        if ! "$APT_UPDATED"; then
            apt-get update
            APT_UPDATED=true
        fi

        DEBIAN_FRONTEND=noninteractive \
            apt-get install -y \
            "${missing_packages[@]}"
    fi
fi


if ! command -v python3 >/dev/null 2>&1; then
    die "python3 is not available."
fi

if ! python3 -c 'import mutagen' >/dev/null 2>&1; then
    die "Python Mutagen is not available."
fi

if ! command -v ffmpeg >/dev/null 2>&1; then
    die "ffmpeg is not available."
fi

if ! command -v ffprobe >/dev/null 2>&1; then
    die "ffprobe is not available."
fi

if ! command -v node >/dev/null 2>&1; then
    die "Node.js is not available."
fi

if ! command -v npm >/dev/null 2>&1; then
    die "npm is not available."
fi


ok "Castillo dependencies are available"


###############################################################################
# Syntax validation
###############################################################################

info "Checking source syntax"

while IFS= read -r php_file; do
    php -l "$php_file" >/dev/null
done < <(
    find "${SCRIPT_DIR}/api" \
        -maxdepth 1 \
        -type f \
        -name '*.php' \
        -print |
    sort
)


python3 - \
    "${SCRIPT_DIR}/backend/waveform.py" \
    "${SCRIPT_DIR}/helpers/castillo-cover-save" \
    "${SCRIPT_DIR}/helpers/castillo-hashtags-index" \
    "${SCRIPT_DIR}/helpers/castillo-lrc-save" \
    "${SCRIPT_DIR}/helpers/castillo-tags-save" <<'PYTHON'
from pathlib import Path
import sys

for filename in sys.argv[1:]:
    source = Path(filename).read_text(
        encoding="utf-8"
    )

    compile(
        source,
        filename,
        "exec"
    )
PYTHON


bash -n \
    "${SCRIPT_DIR}/helpers/castillo-audio-output"


visudo -cf \
    "${SCRIPT_DIR}/config/sudoers-core.template" \
    >/dev/null


ok "Source syntax is valid"


###############################################################################
# --check ends here
###############################################################################

if "$CHECK_ONLY"; then
    printf '\n========================================\n'
    printf ' Castillo Player check completed\n'
    printf '========================================\n\n'

    printf 'No files were installed.\n'
    exit 0
fi


###############################################################################
# Configuration
###############################################################################

info "Preparing configuration"


if [[ -f "$CONFIG_FILE" ]]; then
    ok "Existing configuration will be preserved: $CONFIG_FILE"

else
    if [[ -z "$LIBRARY_ROOT" ]] &&
       [[ -t 0 ]]; then

        printf '\nPhysical path to the local music library:\n'
        printf 'Example: /media/MUSIC_DRIVE/Music\n\n'
        read -r -p '> ' LIBRARY_ROOT
    fi


    if [[ -z "$MPD_PREFIX" ]] &&
       [[ -t 0 ]]; then

        printf '\nMPD path corresponding to that library:\n'
        printf 'Example: USB/MUSIC_DRIVE/Music/\n\n'
        read -r -p '> ' MPD_PREFIX
    fi


    [[ -n "$LIBRARY_ROOT" ]] ||
        die "A library path is required on a new installation."

    [[ -n "$MPD_PREFIX" ]] ||
        die "An MPD library prefix is required on a new installation."


    [[ "$LIBRARY_ROOT" == /* ]] ||
        die "library_root must be an absolute path."


    [[ -d "$LIBRARY_ROOT" ]] ||
        die "The library directory does not exist: $LIBRARY_ROOT"


    [[ "$LIBRARY_ROOT" != "/" ]] ||
        die "library_root cannot be /."


    [[ "$MPD_PREFIX" != /* ]] ||
        die "The MPD prefix must be relative, not an absolute path."


    [[ "$MPD_PREFIX" == */ ]] ||
        MPD_PREFIX="${MPD_PREFIX}/"


    install \
        -d \
        -o root \
        -g root \
        -m 0755 \
        "$CONFIG_DIR"


    config_tmp="$(
        mktemp "${CONFIG_DIR}/.castillo.ini.XXXXXX"
    )"


    cp \
        "${SCRIPT_DIR}/config/castillo.ini.example" \
        "$config_tmp"


    LIBRARY_ROOT_VALUE="$LIBRARY_ROOT" \
    MPD_PREFIX_VALUE="$MPD_PREFIX" \
    python3 - "$config_tmp" <<'PY'
import os
import re
import sys


path = sys.argv[1]

library_root = os.environ["LIBRARY_ROOT_VALUE"]
mpd_prefix = os.environ["MPD_PREFIX_VALUE"]


def quote(value):
    return (
        '"'
        + value
        .replace("\\", "\\\\")
        .replace('"', '\\"')
        + '"'
    )


with open(
    path,
    "r",
    encoding="utf-8"
) as handle:
    lines = handle.readlines()


section = None

found_root = False
found_prefix = False


for index, line in enumerate(lines):
    stripped = line.strip()

    match = re.fullmatch(
        r"\[([^\]]+)\]",
        stripped
    )

    if match:
        section = match.group(1).strip().lower()
        continue


    if section != "core":
        continue


    if re.match(
        r"^\s*library_root\s*=",
        line,
        re.I
    ):
        newline = "\n" if line.endswith("\n") else ""

        lines[index] = (
            "library_root = "
            + quote(library_root)
            + newline
        )

        found_root = True
        continue


    if re.match(
        r"^\s*library_mpd_prefix\s*=",
        line,
        re.I
    ):
        newline = "\n" if line.endswith("\n") else ""

        lines[index] = (
            "library_mpd_prefix = "
            + quote(mpd_prefix)
            + newline
        )

        found_prefix = True


if not found_root:
    raise SystemExit(
        "library_root was not found in the template"
    )


if not found_prefix:
    raise SystemExit(
        "library_mpd_prefix was not found in the template"
    )


with open(
    path,
    "w",
    encoding="utf-8"
) as handle:
    handle.writelines(lines)
PY


    chown root:root "$config_tmp"
    chmod 0644 "$config_tmp"

    mv \
        "$config_tmp" \
        "$CONFIG_FILE"


    ok "Created $CONFIG_FILE"
fi


chown root:root "$CONFIG_FILE"
chmod 0644 "$CONFIG_FILE"


###############################################################################
# Runtime directories
###############################################################################

info "Creating runtime directories"

install \
    -d \
    -o www-data \
    -g www-data \
    -m 0750 \
    "$STATE_DIR"


install \
    -d \
    -o www-data \
    -g www-data \
    -m 0750 \
    "$WAVEFORM_DIR"


ok "Runtime directories are ready"


###############################################################################
# Backend
###############################################################################

info "Installing backend"

install \
    -d \
    -o root \
    -g root \
    -m 0755 \
    "$BACKEND_DIR"


install \
    -o root \
    -g root \
    -m 0644 \
    "${SCRIPT_DIR}/backend/waveform.py" \
    "${BACKEND_DIR}/waveform.py"


ok "Backend installed"


###############################################################################
# Helpers
###############################################################################

info "Installing core helpers"

for helper in \
    castillo-audio-output \
    castillo-cover-save \
    castillo-hashtags-index \
    castillo-lrc-save \
    castillo-tags-save
do
    install \
        -o root \
        -g root \
        -m 0755 \
        "${SCRIPT_DIR}/helpers/${helper}" \
        "${HELPER_DIR}/${helper}"
done


ok "Core helpers installed"


###############################################################################
# Initial hashtag index
###############################################################################

if [[ ! -f "${STATE_DIR}/hashtags.sqlite" ]]; then
    info "Building initial hashtag index"

    if "${HELPER_DIR}/castillo-hashtags-index" --rebuild; then

        if [[ -f "${STATE_DIR}/hashtags.sqlite" ]]; then
            chown                 root:www-data                 "${STATE_DIR}/hashtags.sqlite"

            chmod                 0664                 "${STATE_DIR}/hashtags.sqlite"
        fi

        ok "Hashtag index created"

    else
        warn             "The initial hashtag index could not be built. "             "Castillo Player will still work, but the Hashtags view "             "will remain unavailable until the index is rebuilt."
    fi

else
    ok "Existing hashtag index preserved"
fi


###############################################################################
# Sudoers
###############################################################################

info "Installing core sudoers policy"

sudoers_tmp="$(
    mktemp /etc/sudoers.d/.castillo-player.XXXXXX
)"


install \
    -o root \
    -g root \
    -m 0440 \
    "${SCRIPT_DIR}/config/sudoers-core.template" \
    "$sudoers_tmp"


visudo -cf "$sudoers_tmp" >/dev/null


mv \
    "$sudoers_tmp" \
    "$SUDOERS_FILE"


chown root:root "$SUDOERS_FILE"
chmod 0440 "$SUDOERS_FILE"


visudo -cf "$SUDOERS_FILE" >/dev/null


ok "Core sudoers policy installed"


###############################################################################
# API
###############################################################################

info "Installing Castillo API"

install \
    -d \
    -o root \
    -g root \
    -m 0755 \
    "$API_DIR"


# Remove only PHP files managed by Castillo.
find "$API_DIR" \
    -maxdepth 1 \
    -type f \
    -name '*.php' \
    -delete


while IFS= read -r php_file; do
    install \
        -o root \
        -g root \
        -m 0644 \
        "$php_file" \
        "$API_DIR/"
done < <(
    find "${SCRIPT_DIR}/api" \
        -maxdepth 1 \
        -type f \
        -name '*.php' \
        -print |
    sort
)


while IFS= read -r php_file; do
    php -l "$php_file" >/dev/null
done < <(
    find "$API_DIR" \
        -maxdepth 1 \
        -type f \
        -name '*.php' \
        -print |
    sort
)


ok "Castillo API installed"


###############################################################################
# Frontend build
###############################################################################

info "Building frontend"

FRONTEND_BUILD_DIR="$(
    mktemp -d /tmp/castillo-frontend-build.XXXXXX
)"

cp -a \
    "${SCRIPT_DIR}/frontend/." \
    "${FRONTEND_BUILD_DIR}/"

rm -rf \
    "${FRONTEND_BUILD_DIR}/node_modules" \
    "${FRONTEND_BUILD_DIR}/dist"

pushd "$FRONTEND_BUILD_DIR" >/dev/null

npm ci
npm run build

[[ -f dist/index.html ]] ||
    die "Frontend build did not produce dist/index.html."

popd >/dev/null

ok "Frontend build completed"
###############################################################################
# Frontend deploy
###############################################################################

info "Deploying frontend"

web_stage="$(
    mktemp -d /var/www/.castillo-stage.XXXXXX
)"


cp -a \
    "${FRONTEND_BUILD_DIR}/dist/." \
    "$web_stage/"


chown -R root:root "$web_stage"


find "$web_stage" \
    -type d \
    -exec chmod 0755 {} +


find "$web_stage" \
    -type f \
    -exec chmod 0644 {} +


if [[ -d "$WEB_DIR" ]]; then
    rm -rf "$WEB_DIR"
fi


mv \
    "$web_stage" \
    "$WEB_DIR"


chown root:root "$WEB_DIR"
chmod 0755 "$WEB_DIR"


ok "Frontend deployed to /castillo/"

rm -rf "$FRONTEND_BUILD_DIR"
FRONTEND_BUILD_DIR=""


###############################################################################
# Final validation
###############################################################################

info "Running final checks"


test -f "${WEB_DIR}/index.html" ||
    die "Frontend index.html is missing."


test -f "${API_DIR}/api.php" ||
    die "api.php is missing."


test -f "${BACKEND_DIR}/waveform.py" ||
    die "waveform.py is missing."


for helper in \
    castillo-audio-output \
    castillo-cover-save \
    castillo-hashtags-index \
    castillo-lrc-save \
    castillo-tags-save
do
    test -x "${HELPER_DIR}/${helper}" ||
        die "Helper is not executable: ${helper}"
done


visudo -cf "$SUDOERS_FILE" >/dev/null


if nginx -t >/dev/null 2>&1; then
    ok "nginx configuration is valid"
else
    warn "nginx -t reported an error. Castillo did not modify nginx."
fi


if mpc status >/dev/null 2>&1; then
    ok "MPD responds through mpc"
else
    warn "mpc could not contact MPD."
fi


###############################################################################
# Result
###############################################################################

printf '\n'
printf '========================================\n'
printf ' Castillo Player core installed\n'
printf '========================================\n'
printf '\n'

printf 'Frontend:\n'
printf '  /castillo/\n'
printf '\n'

printf 'API:\n'
printf '  /castillo-api/\n'
printf '\n'

printf 'Configuration:\n'
printf '  %s\n' "$CONFIG_FILE"
printf '\n'

printf 'Runtime:\n'
printf '  %s\n' "$STATE_DIR"
printf '\n'

printf 'NAS synchronization:\n'
printf '  not modified by the core installer\n'
printf '\n'

printf 'moOde root URL:\n'
printf '  unchanged\n'
printf '\n'

if [[ ! -f "${STATE_DIR}/hashtags.sqlite" ]]; then
    warn \
        "The hashtag index does not exist yet. " \
        "It must be generated before the Hashtags view can be used."
fi

printf '\nInstallation completed successfully.\n'
