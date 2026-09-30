#!/bin/bash

set -euo pipefail

worker_timeout=${5:-${WORKER_TIMEOUT:-60}}
retry_after=${REDIS_QUEUE_RETRY_AFTER:-90}
if (( worker_timeout >= retry_after )); then
    echo "WORKER_TIMEOUT must be shorter than REDIS_QUEUE_RETRY_AFTER." >&2
    exit 1
fi

exec ./artisan queue:work ${1:-${WORKER_CONNECTION:-}} --tries=${2:-${WORKER_TRIES:-3}} --sleep=${3:-${WORKER_SLEEP:-3}} --memory=${4:-${WORKER_MEMORY:-128}} --timeout=${5:-${WORKER_TIMEOUT:-60}} --queue=${6:-${WORKER_QUEUE:-default}} --max-time=${7:-${WORKER_MAX_TIME:-3600}} --max-jobs=${8:-${WORKER_MAX_JOBS:-50}} --backoff=${9:-${WORKER_BACKOFF:-10}} --no-ansi --no-interaction
