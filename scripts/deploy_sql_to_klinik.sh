#!/usr/bin/env bash
# =============================================================================
# Transfer SQL bundle files ke server klinik via SCP.
#
# File yang dikirim:
#   - database/sql/install_bundle.sql                 (Laravel + klinik pratama)
#   - database/sql/install_bundle_satusehat.sql       (LOINC + SNOMED, opsional)
#   - database/sql/install_bundle_fitur_lanjutan.sql  (signa, non-medis, user online)
#   - database/sql/README.md                          (dokumentasi urutan run)
#
# Usage:
#   ./scripts/deploy_sql_to_klinik.sh
#   ./scripts/deploy_sql_to_klinik.sh --yes               # skip konfirmasi
#   ./scripts/deploy_sql_to_klinik.sh --host=user@1.2.3.4 # override host
#   ./scripts/deploy_sql_to_klinik.sh --dir=mysql_deploy  # override remote dir
#
# Env override:
#   KLINIK_SSH_HOST   default: klinikmadinah@172.8.9.12
#   KLINIK_SQL_DIR    default: sql_deploy   (relative ke $HOME remote)
#
# Setelah transfer selesai, login & run manual:
#   ssh klinikmadinah@172.8.9.12
#   cd sql_deploy
#   sqlplus siklik/<pwd>@//<host>:1521/<service> @install_bundle.sql
#   sqlplus siklik/<pwd>@//<host>:1521/<service> @install_bundle_satusehat.sql
#   sqlplus siklik/<pwd>@//<host>:1521/<service> @install_bundle_fitur_lanjutan.sql
# =============================================================================

set -euo pipefail

REMOTE_HOST="${KLINIK_SSH_HOST:-klinikmadinah@172.8.9.12}"
REMOTE_DIR="${KLINIK_SQL_DIR:-sql_deploy}"
SKIP_CONFIRM=0

for arg in "$@"; do
    case "$arg" in
        -y|--yes)   SKIP_CONFIRM=1 ;;
        --host=*)   REMOTE_HOST="${arg#--host=}" ;;
        --dir=*)    REMOTE_DIR="${arg#--dir=}" ;;
        -h|--help)
            sed -n '2,28p' "$0" | sed 's/^# \?//'
            exit 0
            ;;
        *)
            echo "Unknown arg: $arg" >&2
            echo "Run with --help untuk usage." >&2
            exit 1
            ;;
    esac
done

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SQL_DIR="$ROOT/database/sql"

FILES=(
    "$SQL_DIR/install_bundle.sql"
    "$SQL_DIR/install_bundle_satusehat.sql"
    "$SQL_DIR/install_bundle_fitur_lanjutan.sql"
    "$SQL_DIR/README.md"
)

# Verify all files exist
for f in "${FILES[@]}"; do
    if [[ ! -f "$f" ]]; then
        echo "❌ File hilang: $f" >&2
        exit 1
    fi
done

echo "▶ Target : $REMOTE_HOST:~/$REMOTE_DIR/"
echo "▶ Files  :"
for f in "${FILES[@]}"; do
    printf "    %-40s  %s\n" "$(basename "$f")" "$(du -h "$f" | cut -f1)"
done
echo

if [[ $SKIP_CONFIRM -eq 0 ]]; then
    read -rp "Lanjut transfer? [y/N] " ans
    [[ "$ans" =~ ^[Yy]$ ]] || { echo "Aborted."; exit 0; }
fi

echo "▶ Pastikan remote dir ada..."
ssh "$REMOTE_HOST" "mkdir -p '$REMOTE_DIR'"

echo "▶ scp -p..."
scp -p "${FILES[@]}" "$REMOTE_HOST:$REMOTE_DIR/"

cat <<EOF

✓ Transfer selesai.

Verify di remote:
  ssh $REMOTE_HOST 'ls -lh $REMOTE_DIR/'

Run bundles (manual, biar password Oracle nggak ke-log):
  ssh $REMOTE_HOST
  cd $REMOTE_DIR
  sqlplus siklik/<pwd>@//<host>:1521/<service> @install_bundle.sql
  sqlplus siklik/<pwd>@//<host>:1521/<service> @install_bundle_satusehat.sql
EOF
