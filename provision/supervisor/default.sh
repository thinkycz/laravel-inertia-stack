#!/bin/bash

set -euo pipefail

exec ./artisan queue:work ${1:-${WORKER_CONNECTION:-}} --tries=${2:-${WORKER_TRIES:-1}} --sleep=${3:-${WORKER_SLEEP:-3}} --memory=${4:-${WORKER_MEMORY:-128}} --timeout=${5:-${WORKER_TIMEOUT:-120}} --queue=${6:-${WORKER_QUEUE:-default}} --max-time=${7:-${WORKER_MAX_TIME:-3600}} --max-jobs=${8:-${WORKER_MAX_JOBS:-50}} --backoff=${9:-${WORKER_BACKOFF:-3600}} --no-ansi --no-interaction
