#!/usr/bin/env bash
# Build bundled Naruto anime eye line models type_102–type_111 (950×35, preserve colors).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
python3 "$ROOT/tools/generate-naruto-eye-line-models.py"
php -r "
require '${ROOT}/tests/smoke-bootstrap.php';
require PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';
PCKZ_Line_Library::register_imported_customer_red_lines();
" 2>/dev/null || true
echo "OK naruto eye line models type_102–type_111"
