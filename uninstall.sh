#!/usr/bin/env bash

set -Eeuo pipefail


###############################################################################
# Castillo Player uninstaller
#
# Default behavior:
#   - removes Castillo frontend/API/backend/helpers
#   - removes optional NAS helpers/systemd/sudoers when present
#   - stops/disables the NAS timer
#   - restores moOde "/" only when the known Castillo redirect is present
#   - preserves configuration and runtime data
#
# Optional:
#   --purge-config    remove /etc/castillo-player
#   --purge-data      remove Castillo runtime data
#   --purge-all       purge configuration + runtime data
#   --check           show what would be done without changing anything
###############################################################################


WEB_DIR="/var/www/castillo"
API_DIR="/var/www/castillo-api"

INSTALL_DIR="/opt/castillo-player"
BACKEND_DIR="${INSTALL_DIR}/backend"

CONFIG_DIR="/etc/castillo-player"

STATE_DIR="/var/lib/castillo-player"
NAS_STATE_DIR="/var/lib/nas-music-sync"

CORE_SUDOERS="/etc/sudoers.d/castillo-player"
NAS_SUDOERS="/etc/sudoers.d/castillo-player-nas"

CORE_HELPERS=(
    "/usr/local/sbin/castillo-audio-output"
    "/usr/local/sbin/castillo-cover-save"
    "/usr/local/sbin/castillo-hashtags-index"
    "/usr/local/sbin/castillo-lrc-save"
    "/usr/local/sbin/castillo-tags-save"
)

NAS_HELPERS=(
    "/usr/local/sbin/castillo-pending-change"
    "/usr/local/sbin/castillo-pending-check"
    "/usr/local/sbin/castillo-pending-sync"
    "/usr/local/sbin/nas-music-sync"
)

NAS_SERVICE="/etc/systemd/system/nas-music-sync.service"
NAS_TIMER="/etc/systemd/system/nas-music-sync.timer"

NGINX_MOODE_HTTP="/etc/nginx/sites-available/moode-http.conf"

CHECK_ONLY=false
PURGE_CONFIG=false
PURGE_DATA=false


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
Castillo Player uninstaller

Usage:
  sudo ./uninstall.sh [options]

Options:
  --check
      Show the uninstall plan without changing the system.

  --purge-config
      Also remove /etc/castillo-player.

  --purge-data
      Also remove:
        /var/lib/castillo-player
        /var/lib/nas-music-sync

  --purge-all
      Equivalent to --purge-config --purge-data.

  -h, --help
      Show this help.

Default behavior preserves configuration and runtime data.
USAGE
}


while [[ $# -gt 0 ]]; do
    case "$1" in
        --check)
            CHECK_ONLY=true
            shift
            ;;

        --purge-config)
            PURGE_CONFIG=true
            shift
            ;;

        --purge-data)
            PURGE_DATA=true
            shift
            ;;

        --purge-all)
            PURGE_CONFIG=true
            PURGE_DATA=true
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
    die "Run this uninstaller with sudo."


###############################################################################
# Helpers
###############################################################################

planned_remove()
{
    local path="$1"

    if [[ -e "$path" || -L "$path" ]]; then
        printf '  REMOVE   %s\n' "$path"
    fi
}


remove_path()
{
    local path="$1"

    if [[ ! -e "$path" && ! -L "$path" ]]; then
        return 0
    fi

    rm -rf -- "$path"
}


###############################################################################
# Detect known Castillo nginx redirect
###############################################################################

has_castillo_redirect()
{
    [[ -f "$NGINX_MOODE_HTTP" ]] || return 1

    python3 - "$NGINX_MOODE_HTTP" <<'PY'
from pathlib import Path
import re
import sys

text = Path(sys.argv[1]).read_text(
    encoding="utf-8"
)

pattern = re.compile(
    r'''
    location\s*=\s*/\s*\{
        \s*
        return\s+302\s+/castillo/\s*;
        \s*
    \}
    ''',
    re.X | re.S,
)

raise SystemExit(
    0 if pattern.search(text) else 1
)
PY
}


restore_moode_root()
{
    local backup
    local candidate

    backup="$(
        mktemp /etc/nginx/.moode-http.castillo-backup.XXXXXX
    )"

    candidate="$(
        mktemp /etc/nginx/.moode-http.castillo-candidate.XXXXXX
    )"

    cp -a \
        "$NGINX_MOODE_HTTP" \
        "$backup"


    python3 - \
        "$NGINX_MOODE_HTTP" \
        "$candidate" <<'PY'
from pathlib import Path
import re
import sys


source = Path(sys.argv[1])
destination = Path(sys.argv[2])

text = source.read_text(
    encoding="utf-8"
)


block = re.compile(
    r'''(?mx)

    ^[ \t]*\#[ \t]*\n
    ^[ \t]*\#[ \t]*Castillo[ ]Player[ ]como[ ]interfaz[ ]principal\.[ \t]*\n
    ^[ \t]*\#[ \t]*\n
    ^[ \t]*\#[ \t]*Solo[ ]afecta[ ]exactamente[ ]a[ ]"/"\.[ \t]*\n
    ^[ \t]*\#[ \t]*Las[ ]páginas[ ]originales[ ]de[ ]moOde,[ \t]*\n
    ^[ \t]*\#[ \t]*PHP,[ ]coverart,[ ]CamillaDSP,[ ]etc\.[ \t]*\n
    ^[ \t]*\#[ \t]*siguen[ ]funcionando[ ]normalmente\.[ \t]*\n
    ^[ \t]*\#[ \t]*\n
    ^[ \t]*location[ \t]*=[ \t]*/[ \t]*\{[ \t]*\n
    ^[ \t]*return[ \t]+302[ \t]+/castillo/[ \t]*;[ \t]*\n
    ^[ \t]*\}[ \t]*\n
    (?:^[ \t]*\n)?
    '''
)


new_text, count = block.subn(
    "",
    text,
    count=1
)


if count == 0:
    fallback = re.compile(
        r'''(?msx)
        ^[ \t]*location[ \t]*=[ \t]*/[ \t]*\{
        [ \t\r\n]*
        return[ \t]+302[ \t]+/castillo/[ \t]*;
        [ \t\r\n]*
        \}[ \t]*\n?
        '''
    )

    new_text, count = fallback.subn(
        "",
        text,
        count=1
    )


if count != 1:
    raise SystemExit(
        "No se encontró exactamente un redirect Castillo."
    )


destination.write_text(
    new_text,
    encoding="utf-8"
)
PY


    cp \
        "$candidate" \
        "$NGINX_MOODE_HTTP"


    if nginx -t >/dev/null 2>&1; then
        rm -f "$candidate"
        rm -f "$backup"

        systemctl reload nginx

        return 0
    fi


    warn "nginx validation failed; restoring previous configuration."

    cp \
        "$backup" \
        "$NGINX_MOODE_HTTP"

    rm -f "$candidate"
    rm -f "$backup"

    nginx -t >/dev/null 2>&1 || true

    return 1
}


###############################################################################
# Check mode
###############################################################################

if "$CHECK_ONLY"; then
    printf '\n========================================\n'
    printf ' Castillo Player uninstall plan\n'
    printf '========================================\n\n'

    printf 'Core files:\n'

    planned_remove "$WEB_DIR"
    planned_remove "$API_DIR"
    planned_remove "$BACKEND_DIR"
    planned_remove "$CORE_SUDOERS"

    for path in "${CORE_HELPERS[@]}"; do
        planned_remove "$path"
    done


    printf '\nOptional NAS module:\n'

    planned_remove "$NAS_SUDOERS"
    planned_remove "$NAS_SERVICE"
    planned_remove "$NAS_TIMER"

    for path in "${NAS_HELPERS[@]}"; do
        planned_remove "$path"
    done


    printf '\nmoOde root:\n'

    if has_castillo_redirect; then
        printf '  RESTORE  %s\n' "$NGINX_MOODE_HTTP"
    else
        printf '  unchanged\n'
    fi


    printf '\nConfiguration:\n'

    if "$PURGE_CONFIG"; then
        planned_remove "$CONFIG_DIR"
    else
        printf '  PRESERVE %s\n' "$CONFIG_DIR"
        printf '  UPDATE   %s/castillo.ini: nas.enabled = false\n' "$CONFIG_DIR"
    fi


    printf '\nRuntime data:\n'

    if "$PURGE_DATA"; then
        planned_remove "$STATE_DIR"
        planned_remove "$NAS_STATE_DIR"
    else
        printf '  PRESERVE %s\n' "$STATE_DIR"
        printf '  PRESERVE %s\n' "$NAS_STATE_DIR"
    fi


    printf '\nNo files were changed.\n'

    exit 0
fi


###############################################################################
# Stop NAS automation first
###############################################################################

info "Stopping optional NAS synchronization"


if systemctl list-unit-files \
    nas-music-sync.timer \
    >/dev/null 2>&1; then

    systemctl disable --now \
        nas-music-sync.timer \
        >/dev/null 2>&1 || true
fi


if systemctl list-unit-files \
    nas-music-sync.service \
    >/dev/null 2>&1; then

    systemctl stop \
        nas-music-sync.service \
        >/dev/null 2>&1 || true
fi


ok "NAS synchronization stopped"


###############################################################################
# Restore moOde root before removing frontend
###############################################################################

if has_castillo_redirect; then
    info "Restoring moOde root URL"

    restore_moode_root ||
        die "Could not safely restore the moOde root URL."

    ok "moOde root URL restored"

else
    ok "moOde root URL does not contain the Castillo redirect"
fi


###############################################################################
# Remove systemd NAS units
###############################################################################

info "Removing NAS systemd units"

remove_path "$NAS_SERVICE"
remove_path "$NAS_TIMER"

systemctl daemon-reload

ok "NAS systemd units removed"


###############################################################################
# Remove sudoers
###############################################################################

info "Removing Castillo sudoers policies"

remove_path "$NAS_SUDOERS"
remove_path "$CORE_SUDOERS"

# Removing Castillo-owned sudoers files cannot introduce
# a syntax error into unrelated sudoers configuration.
# Do not run a global visudo check here because unrelated
# administrator files may have independent warnings/errors.

ok "Castillo sudoers policies removed"


###############################################################################
# Remove helpers
###############################################################################

info "Removing Castillo helpers"

for path in "${NAS_HELPERS[@]}"; do
    remove_path "$path"
done

for path in "${CORE_HELPERS[@]}"; do
    remove_path "$path"
done

ok "Castillo helpers removed"


###############################################################################
# Remove web/backend
###############################################################################

info "Removing Castillo application files"

remove_path "$WEB_DIR"
remove_path "$API_DIR"
remove_path "$BACKEND_DIR"


if [[ -d "$INSTALL_DIR" ]]; then
    if [[ -z "$(find "$INSTALL_DIR" -mindepth 1 -maxdepth 1 -print -quit)" ]]; then
        rmdir "$INSTALL_DIR"
    fi
fi


ok "Castillo application files removed"


###############################################################################
# Disable optional NAS module in preserved configuration
###############################################################################

disable_nas_in_preserved_config()
{
    [[ -f "${CONFIG_DIR}/castillo.ini" ]] ||
        return 0

    python3 - "${CONFIG_DIR}/castillo.ini" <<'PYTHON'
from pathlib import Path
import re
import sys

path = Path(sys.argv[1])

text = path.read_text(
    encoding="utf-8"
)

section = None

lines = text.splitlines(
    keepends=True
)

found = False

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

    if re.match(
        r"^\s*enabled\s*=",
        line,
        re.I
    ):
        newline = "\n" if line.endswith("\n") else ""

        lines[index] = (
            "enabled = false"
            + newline
        )

        found = True
        break

if not found:
    raise SystemExit(
        "No se encontró nas.enabled en castillo.ini"
    )

path.write_text(
    "".join(lines),
    encoding="utf-8"
)
PYTHON

    chown \
        root:root \
        "${CONFIG_DIR}/castillo.ini"

    chmod \
        0644 \
        "${CONFIG_DIR}/castillo.ini"
}


if ! "$PURGE_CONFIG"; then
    info "Disabling removed NAS module in preserved configuration"

    disable_nas_in_preserved_config

    ok "NAS module disabled in preserved configuration"
fi


###############################################################################
# Optional purge
###############################################################################

if "$PURGE_CONFIG"; then
    info "Removing Castillo configuration"

    remove_path "$CONFIG_DIR"

    ok "Configuration removed"

else
    ok "Configuration preserved: $CONFIG_DIR"
fi


if "$PURGE_DATA"; then
    info "Removing Castillo runtime data"

    remove_path "$STATE_DIR"
    remove_path "$NAS_STATE_DIR"

    ok "Runtime data removed"

else
    ok "Runtime data preserved"

    printf '     %s\n' "$STATE_DIR"
    printf '     %s\n' "$NAS_STATE_DIR"
fi


###############################################################################
# Final validation
###############################################################################

info "Running final checks"


if [[ -f "$NGINX_MOODE_HTTP" ]]; then
    nginx -t >/dev/null ||
        die "nginx configuration is invalid."
fi


systemctl daemon-reload


###############################################################################
# Result
###############################################################################

printf '\n'
printf '========================================\n'
printf ' Castillo Player removed\n'
printf '========================================\n'
printf '\n'

printf 'moOde web interface:\n'
printf '  restored / unchanged\n'

printf '\nConfiguration:\n'

if "$PURGE_CONFIG"; then
    printf '  removed\n'
else
    printf '  preserved\n'
fi

printf '\nRuntime data:\n'

if "$PURGE_DATA"; then
    printf '  removed\n'
else
    printf '  preserved\n'
fi

printf '\nUninstall completed successfully.\n'
