#!/usr/bin/env bash
set -euo pipefail
node --check "$(dirname "$0")/../public/js/preview-engine.js"
echo "OK preview-engine.js syntax"
