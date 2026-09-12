#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(
    cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1
    pwd
)"

CONFIG_FILE="/etc/castillo-player/castillo.ini"

HELPER_DIR="/usr/local/sbin"
STATE_DIR="/var/lib/nas-music-sync"

SUDOERS_FILE="/etc/sudoers.d/castillo-player-nas"

SERVICE_FILE="/etc/systemd/system/nas-music-sync.service"
TIMER_FILE="/etc/systemd/system/nas-music-sync.timer"

SOURCE_NAME=""
READONLY_ROOT=""
READWRITE_ROOT=""

CHECK_ONLY=false
NO_APT=false
ENABLE_TIMER=true


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
Castillo Player optional NAS synchronization installer

Usage:
  sudo ./install-nas-sync.sh [options]

Options:
  --source-name NAME
      NAS source name configured in moOde.

  --readonly-root PATH
      Existing read-only NAS mount used for NAS -> local sync.

  --readwrite-root PATH
      Temporary read/write mount path used for local -> NAS sync.

  --check
      Validate requirements without changing the system.

  --no-apt
      Do not install missing packages.

  --no-enable-timer
      Install the timer but do not enable it.

  -h, --help
      Show this help.

Example:

  sudo ./install-nas-sync.sh \
      --source-name "MusicNAS" \
      --readonly-root "/mnt/NAS/MusicNAS" \
      --readwrite-root "/mnt/NAS/MusicNAS_RW"
USAGE
}


while [[ $# -gt 0 ]]; do
    case "$1" in
        --source-name)
            [[ $# -ge 2 ]] ||
                die "--source-name requires a value."

            SOURCE_NAME="$2"
            shift 2
            ;;

        --readonly-root)
            [[ $# -ge 2 ]] ||
                die "--readonly-root requires a value."

            READONLY_ROOT="$2"
            shift 2
            ;;

        --readwrite-root)
            [[ $# -ge 2 ]] ||
                die "--readwrite-root requires a value."

            READWRITE_ROOT="$2"
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

        --no-enable-timer)
            ENABLE_TIMER=false
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


[[ "$EUID" -eq 0 ]] ||
    die "Run this installer with sudo."


###############################################################################
# Source tree
###############################################################################

info "Validating NAS module source tree"

required_files=(
    "${SCRIPT_DIR}/castillo-pending-change"
    "${SCRIPT_DIR}/castillo-pending-check"
    "${SCRIPT_DIR}/castillo-pending-sync"
    "${SCRIPT_DIR}/nas-music-sync"
    "${SCRIPT_DIR}/sudoers/castillo-player-nas.template"
    "${SCRIPT_DIR}/systemd/nas-music-sync.service"
    "${SCRIPT_DIR}/systemd/nas-music-sync.timer"
)

for path in "${required_files[@]}"; do
    [[ -f "$path" ]] ||
        die "Required file not found: $path"
done

ok "NAS module source tree is complete"


###############################################################################
# Core installation
###############################################################################

info "Validating Castillo Player core"

[[ -f "$CONFIG_FILE" ]] ||
    die "Castillo Player core is not configured. Run the core installer first."

for cmd in \
    php \
    python3 \
    sudo \
    visudo \
    systemctl \
    mpc
do
    command -v "$cmd" >/dev/null 2>&1 ||
        die "Required core command not found: $cmd"
done


php -r '
$c = parse_ini_file(
    $argv[1],
    true,
    INI_SCANNER_TYPED
);

exit($c === false ? 1 : 0);
' "$CONFIG_FILE" ||
    die "Castillo configuration is not a valid INI file."

ok "Castillo Player core is available"


###############################################################################
# Dependencies
###############################################################################

info "Checking NAS synchronization dependencies"

missing_packages=()

command -v rsync >/dev/null 2>&1 ||
    missing_packages+=("rsync")

command -v mount.cifs >/dev/null 2>&1 ||
    missing_packages+=("cifs-utils")

command -v findmnt >/dev/null 2>&1 ||
    missing_packages+=("util-linux")

command -v mountpoint >/dev/null 2>&1 ||
    missing_packages+=("util-linux")

command -v umount >/dev/null 2>&1 ||
    missing_packages+=("mount")


if (( ${#missing_packages[@]} > 0 )); then
    mapfile -t missing_packages < <(
        printf '%s\n' "${missing_packages[@]}" |
        awk '!seen[$0]++'
    )

    printf '\nMissing packages:\n'
    printf '  - %s\n' "${missing_packages[@]}"

    if "$CHECK_ONLY"; then
        warn "Some NAS dependencies are missing."

    elif "$NO_APT"; then
        die "Dependencies are missing and --no-apt was specified."

    else
        apt-get update

        DEBIAN_FRONTEND=noninteractive \
            apt-get install -y \
            "${missing_packages[@]}"
    fi
fi


for cmd in \
    rsync \
    mount.cifs \
    findmnt \
    mountpoint \
    umount
do
    command -v "$cmd" >/dev/null 2>&1 ||
        die "Required NAS command not found: $cmd"
done

ok "NAS dependencies are available"


###############################################################################
# Syntax
###############################################################################

info "Checking NAS module syntax"

python3 - \
    "${SCRIPT_DIR}/castillo-pending-change" \
    "${SCRIPT_DIR}/castillo-pending-check" \
    "${SCRIPT_DIR}/castillo-pending-sync" <<'PY'
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
PY


bash -n \
    "${SCRIPT_DIR}/nas-music-sync"


visudo -cf \
    "${SCRIPT_DIR}/sudoers/castillo-player-nas.template" \
    >/dev/null


ok "NAS module syntax is valid"


###############################################################################
# Read existing configuration
###############################################################################

ini_value()
{
    php -r '
$c = parse_ini_file(
    $argv[1],
    true,
    INI_SCANNER_TYPED
);

$v = $c[$argv[2]][$argv[3]] ?? "";

if (is_bool($v)) {
    echo $v ? "true" : "false";
} else {
    echo $v;
}
' \
        "$CONFIG_FILE" \
        "$1" \
        "$2"
}


CURRENT_ENABLED="$(ini_value nas enabled)"
CURRENT_SOURCE_NAME="$(ini_value nas source_name)"
CURRENT_READONLY_ROOT="$(ini_value nas readonly_root)"
CURRENT_READWRITE_ROOT="$(ini_value nas readwrite_root)"


printf '\nCurrent NAS configuration:\n'
printf '  enabled:        %s\n' "$CURRENT_ENABLED"
printf '  source_name:    %s\n' "$CURRENT_SOURCE_NAME"
printf '  readonly_root:  %s\n' "$CURRENT_READONLY_ROOT"
printf '  readwrite_root: %s\n' "$CURRENT_READWRITE_ROOT"


###############################################################################
# --check ends here
###############################################################################

if "$CHECK_ONLY"; then
    printf '\n========================================\n'
    printf ' Castillo NAS check completed\n'
    printf '========================================\n\n'
    printf 'No files were installed.\n'
    exit 0
fi


###############################################################################
# Resolve configuration
###############################################################################

if [[ -z "$SOURCE_NAME" ]]; then
    SOURCE_NAME="$CURRENT_SOURCE_NAME"
fi

if [[ -z "$READONLY_ROOT" ]]; then
    READONLY_ROOT="$CURRENT_READONLY_ROOT"
fi

if [[ -z "$READWRITE_ROOT" ]]; then
    READWRITE_ROOT="$CURRENT_READWRITE_ROOT"
fi


if [[ -z "$SOURCE_NAME" ||
      "$SOURCE_NAME" == "YOUR_NAS_SOURCE" ]]; then

    if [[ -t 0 ]]; then
        printf '\nNAS source name configured in moOde:\n'
        read -r -p '> ' SOURCE_NAME
    fi
fi


if [[ -z "$READONLY_ROOT" ||
      "$READONLY_ROOT" == "/mnt/NAS/Music" ]]; then

    if [[ -t 0 ]]; then
        printf '\nExisting read-only NAS mount:\n'
        read -r -p '> ' READONLY_ROOT
    fi
fi


if [[ -z "$READWRITE_ROOT" ||
      "$READWRITE_ROOT" == "/mnt/NAS/Music_RW" ]]; then

    if [[ -t 0 ]]; then
        printf '\nTemporary read/write NAS mount path:\n'
        read -r -p '> ' READWRITE_ROOT
    fi
fi


[[ -n "$SOURCE_NAME" ]] ||
    die "NAS source name is required."

[[ "$SOURCE_NAME" != "YOUR_NAS_SOURCE" ]] ||
    die "Replace the placeholder NAS source name."


[[ "$READONLY_ROOT" == /* ]] ||
    die "readonly_root must be an absolute path."

[[ "$READWRITE_ROOT" == /* ]] ||
    die "readwrite_root must be an absolute path."

[[ "$READONLY_ROOT" != "/" ]] ||
    die "readonly_root cannot be /."

[[ "$READWRITE_ROOT" != "/" ]] ||
    die "readwrite_root cannot be /."

[[ "$READONLY_ROOT" != "$READWRITE_ROOT" ]] ||
    die "Read-only and read/write NAS paths must be different."


[[ -d "$READONLY_ROOT" ]] ||
    die "Read-only NAS path does not exist: $READONLY_ROOT"


mountpoint -q "$READONLY_ROOT" ||
    die \
        "The read-only NAS path is not mounted: $READONLY_ROOT"


###############################################################################
# Install helpers
###############################################################################

info "Installing NAS helpers"

for helper in \
    castillo-pending-change \
    castillo-pending-check \
    castillo-pending-sync \
    nas-music-sync
do
    install \
        -o root \
        -g root \
        -m 0755 \
        "${SCRIPT_DIR}/${helper}" \
        "${HELPER_DIR}/${helper}"
done


install \
    -d \
    -o root \
    -g root \
    -m 0755 \
    "$STATE_DIR"


ok "NAS helpers installed"


###############################################################################
# Sudoers
###############################################################################

info "Installing NAS sudoers policy"

sudoers_tmp="$(
    mktemp /etc/sudoers.d/.castillo-player-nas.XXXXXX
)"


install \
    -o root \
    -g root \
    -m 0440 \
    "${SCRIPT_DIR}/sudoers/castillo-player-nas.template" \
    "$sudoers_tmp"


visudo -cf "$sudoers_tmp" >/dev/null


mv \
    "$sudoers_tmp" \
    "$SUDOERS_FILE"


chown root:root "$SUDOERS_FILE"
chmod 0440 "$SUDOERS_FILE"

visudo -cf "$SUDOERS_FILE" >/dev/null


ok "NAS sudoers policy installed"


###############################################################################
# Systemd
###############################################################################

info "Installing NAS systemd units"

install \
    -o root \
    -g root \
    -m 0644 \
    "${SCRIPT_DIR}/systemd/nas-music-sync.service" \
    "$SERVICE_FILE"


install \
    -o root \
    -g root \
    -m 0644 \
    "${SCRIPT_DIR}/systemd/nas-music-sync.timer" \
    "$TIMER_FILE"


systemctl daemon-reload

ok "NAS systemd units installed"


###############################################################################
# Update Castillo configuration
###############################################################################

info "Enabling NAS module in Castillo configuration"

SOURCE_NAME_VALUE="$SOURCE_NAME" \
READONLY_ROOT_VALUE="$READONLY_ROOT" \
READWRITE_ROOT_VALUE="$READWRITE_ROOT" \
python3 - "$CONFIG_FILE" <<'PY'
from pathlib import Path
import os
import re
import sys


path = Path(sys.argv[1])

values = {
    "enabled": "true",
    "source_name": os.environ["SOURCE_NAME_VALUE"],
    "readonly_root": os.environ["READONLY_ROOT_VALUE"],
    "readwrite_root": os.environ["READWRITE_ROOT_VALUE"],
}


def quote(value):
    return (
        '"'
        + value
        .replace("\\", "\\\\")
        .replace('"', '\\"')
        + '"'
    )


lines = path.read_text(
    encoding="utf-8"
).splitlines(
    keepends=True
)


section = None

found = {
    key: False
    for key in values
}


for index, line in enumerate(lines):
    stripped = line.strip()

    match = re.fullmatch(
        r"\[([^\]]+)\]",
        stripped
    )

    if match:
        section = match.group(1).strip().lower()
        continue


    if section != "nas":
        continue


    for key, value in values.items():
        if not re.match(
            rf"^\s*{re.escape(key)}\s*=",
            line,
            re.I
        ):
            continue

        newline = "\n" if line.endswith("\n") else ""

        if key == "enabled":
            replacement = "enabled = true"
        else:
            replacement = (
                key
                + " = "
                + quote(value)
            )

        lines[index] = replacement + newline
        found[key] = True
        break


missing = [
    key
    for key, present in found.items()
    if not present
]

if missing:
    raise SystemExit(
        "Missing NAS configuration keys: "
        + ", ".join(missing)
    )


path.write_text(
    "".join(lines),
    encoding="utf-8"
)
PY


chown root:root "$CONFIG_FILE"
chmod 0644 "$CONFIG_FILE"


php -r '
$c = parse_ini_file(
    $argv[1],
    true,
    INI_SCANNER_TYPED
);

if (
    $c === false ||
    ($c["nas"]["enabled"] ?? null) !== true
) {
    exit(1);
}
' "$CONFIG_FILE" ||
    die "NAS configuration could not be validated."


ok "NAS module enabled in Castillo configuration"


###############################################################################
# Timer
###############################################################################

if "$ENABLE_TIMER"; then
    info "Enabling NAS synchronization timer"

    systemctl enable \
        nas-music-sync.timer \
        >/dev/null

    ok "NAS timer enabled"

    if systemctl is-active \
        --quiet \
        nas-music-sync.timer; then

        ok "NAS timer was already active"
    else
        warn \
            "NAS timer was not started automatically. " \
            "Run a dry-run first, then start it manually."
    fi

else
    warn "NAS timer installation completed; timer enable step was skipped."
fi


###############################################################################
# Final checks
###############################################################################

info "Running final NAS checks"

visudo -cf "$SUDOERS_FILE" >/dev/null


for helper in \
    castillo-pending-change \
    castillo-pending-check \
    castillo-pending-sync \
    nas-music-sync
do
    test -x "${HELPER_DIR}/${helper}" ||
        die "Installed helper is not executable: $helper"
done


systemctl daemon-reload


ok "NAS module installation is valid"


###############################################################################
# Result
###############################################################################

printf '\n'
printf '========================================\n'
printf ' Castillo NAS module installed\n'
printf '========================================\n'
printf '\n'

printf 'NAS source:\n'
printf '  %s\n' "$SOURCE_NAME"

printf '\nRead-only mount:\n'
printf '  %s\n' "$READONLY_ROOT"

printf '\nRead/write mount:\n'
printf '  %s\n' "$READWRITE_ROOT"

printf '\nTimer:\n'

if "$ENABLE_TIMER"; then
    printf '  enabled\n'
else
    printf '  enable step skipped\n'
fi

printf '\n'
printf 'No NAS synchronization was started by this installer.\n'
printf '\n'
printf 'Before starting the timer, run:\n'
printf '  sudo /usr/local/sbin/nas-music-sync --force --dry-run\n'
printf '\n'
printf 'Then, if the dry-run is correct:\n'
printf '  sudo systemctl start nas-music-sync.timer\n'
printf '\n'
