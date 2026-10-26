#!/usr/bin/env bash
# Backs up the MySQL/MariaDB Farm ERP database to a timestamped .sql.gz
# file under storage/backups/. Reads connection details from .env so the
# password is never typed on the command line or committed to a script.
#
# Usage: bash scripts/backup-mysql.sh
# Requires: mysqldump on PATH (included with XAMPP's mysql/bin).

set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
    echo "No .env file found. Copy .env.example to .env and configure it first." >&2
    exit 1
fi

# shellcheck disable=SC1091
set -a; source .env; set +a

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-farm_erp}"
DB_USERNAME="${DB_USERNAME:-root}"

mkdir -p storage/backups
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
OUT="storage/backups/farm_erp_${TIMESTAMP}.sql.gz"

echo "Backing up database '${DB_DATABASE}' from ${DB_HOST}:${DB_PORT} ..."
MYSQL_PWD="${DB_PASSWORD:-}" mysqldump \
    --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" \
    --single-transaction --routines --triggers \
    "$DB_DATABASE" | gzip > "$OUT"

echo "Backup written to: $OUT"
echo "Restore with: bash scripts/restore-mysql.sh $OUT"
