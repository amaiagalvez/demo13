#!/usr/bin/env bash

output_file="$(mktemp)"
summary_file="storage/logs/test-coverage-summary.txt"

trap 'rm -f "$output_file"' EXIT

XDEBUG_MODE=coverage composer test:coverage 2>&1 | tee "$output_file"
test_status=${PIPESTATUS[0]}

if [[ "$test_status" -ne 0 ]]; then
    exit "$test_status"
fi

summary="$(sed -E 's/\x1B\[[0-9;?]*[ -/]*[@-~]//g; s/\r$//' "$output_file" \
    | grep -E 'Tests:|Duration:|Total:' \
    | sed -E 's/^[[:space:]]+//; s/[[:space:]]+$//')"

if [[ "$summary" != *Tests:* || "$summary" != *Duration:* || "$summary" != *Total:* ]]; then
    printf '%s\n' 'Coverage summary could not be extracted from test output.' >&2
    exit 1
fi

{
    printf 'Executed at: %s\n' "$(date '+%Y-%m-%d %H:%M:%S %Z')"
    printf '%s\n' "$summary"
} >> "$summary_file"
