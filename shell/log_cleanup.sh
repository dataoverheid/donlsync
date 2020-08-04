#!/usr/bin/env bash

set -e
set -u

cd "$(dirname "$0")/../"

find ./log -type f -name "*.zip" -mtime +14 -delete;
