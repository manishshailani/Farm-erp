#!/usr/bin/env bash
# Restores a Farm ERP MySQL/MariaDB backup created by backup-mysql.sh.
#
# Usage: bash scripts/restore-mysql.sh storage/backups/farm_erp_20260101_120000.sql.gz
#
# SAFETY: this OVERWRITES the target database named in .env. It refuses
# to run against a database whose name contains "prod" unless you also
# pass --force, as a speed bump against restoring over production by
# mistake. There is no way for this script to truly know which database
# is "production" -- that judgement is still yours.

set -euo pipefail
cd "$(dirname "$0")/.."

BACKUP_FILE="${1:-}"
FORCE="${2:-}"

if [ -z "$BACKUP_FILE" ] || [ ! -f "$BACKUP_FILE" ]; then
    echo "Usage: bash scripts/restore-mysql.sh <path-to-backup.sql.gz> [--force]" >&2
    exit 1
fi

if [ ! -f .env ]; then
    echo "No .env file found." >&2
    exit 1
fi
# shellcheck disable=SC1091
set -a; source .env; set +a

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-farm_erp}"
DB_USERNAME="${DB_USERNAME:-root}"

if [[ "$DB_DATABASE" == *prod* ]] && [ "$FORCE" != "--force" ]; then
    echo "Refusing to restore over a database named '$DB_DATABASE' (looks like production)." >&2
    echo "Re-run with --force if you are certain." >&2
    exit 1
fi

read -r -p "This will REPLACE all data in database '$DB_DATABASE'. Type 'yes' to continue: " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    echo "Aborted."
    exit 1
fi

echo "Restoring $BACKUP_FILE into '$DB_DATABASE' ..."
gunzip -c "$BACKUP_FILE" | MYSQL_PWD="${DB_PASSWORD:-}" mysql \
    --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" "$DB_DATABASE"

echo "Restore complete."
