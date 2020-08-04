#!/usr/bin/env bash

set -e
set -u

if [ $# == 0 ]; then
    echo Usage: "$(basename "$0")" /path/to/logging/directory >&1
    exit 0
fi

cd "$(dirname "$0")/../"

TMPDIR="${TMPDIR:-/tmp}"
LOCK_FILE="${TMPDIR}/donlsync_lock"

if [ ! -e "${LOCK_FILE}" ]; then

    touch "${LOCK_FILE}"

    # shellcheck disable=SC2064
    trap "rm -f ${LOCK_FILE}" EXIT

    declare -a catalogs=("CBS" "CBSDerden" "NGR" "NMGN" "RDW")

    for catalog in "${catalogs[@]}";
    do
        php DonlSync AnalyzeDatabase \
            --catalog="${catalog}" \
            > "$1/${catalog}__analysis.log"
    done

    for catalog in "${catalogs[@]}";
    do
        php DonlSync SynchronizeCatalog \
            --catalog="${catalog}" \
            --no-analyze \
            > "$1/${catalog}__import.log"
    done

else

    echo "DonlSync is already running; (${LOCK_FILE} exists)"

fi
