#!/usr/bin/env bash

set -eu

cd "$(dirname "$0")/../"

TMPDIR="${TMPDIR:-/tmp}"
LOCK_FILE="${TMPDIR}/donlsync_lock"
CURRENT_DATE=$(date +%Y%m%d)

if [ ! -e "${LOCK_FILE}" ]; then

    touch "${LOCK_FILE}"

    # shellcheck disable=SC2064
    trap "rm -f ${LOCK_FILE}" EXIT

    declare -a catalogs=(
        "CBS"
        "CBSDerden"
        "Eindhoven"
        "NGR"
        "RDW"
        "SC"
    )

    echo "{}" > "log/summary__${CURRENT_DATE}.json"

    for catalog in "${catalogs[@]}";
    do
        php DonlSync AnalyzeDatabase \
            --catalog="${catalog}" \
            > "log/${catalog}__analysis__${CURRENT_DATE}.log"
    done

    for catalog in "${catalogs[@]}";
    do
        php DonlSync SynchronizeCatalog \
            --catalog="${catalog}" \
            --no-analyze \
            --scheduled="${CURRENT_DATE}" \
            > "log/${catalog}__import__${CURRENT_DATE}.log"
    done

    php DonlSync SendLogs \
        --date="${CURRENT_DATE}"

else

    echo "DonlSync is already running; (${LOCK_FILE} exists)"

fi
