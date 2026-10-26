#!/usr/bin/env bash
# Backs up the SQLite database file (only relevant if you are running
# with DB_CONNECTION=sqlite -- normally only the automated test suite
# does this; a real deployment should use MySQL and
# backup-mysql.sh/restore-mysql.sh instead).
#
# Checkpoints WAL into the main file before copying -- SQLite's WAL mode
# can leave recently committed data in a separate -wal file, so a plain
# file copy without this step can silently produce a backup that is
# missing recent transactions. This exact bug was caught by this
# project's own automated test suite (see tests/ReportsAndBackupTest.php)
# before this script was written to account for it.

set -euo pipefail
cd "$(dirname "$0")/.."
set -a; [ -f .env ] && source .env; set +a

DB_SQLITE_PATH="${DB_SQLITE_PATH:-storage/database.sqlite}"
if [ ! -f "$DB_SQLITE_PATH" ]; then
    echo "SQLite database not found at $DB_SQLITE_PATH" >&2
    exit 1
fi

php -r '$pdo = new PDO("sqlite:" . $argv[1]); $pdo->exec("PRAGMA wal_checkpoint(FULL)");' "$DB_SQLITE_PATH"

mkdir -p storage/backups
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
OUT="storage/backups/farm_erp_${TIMESTAMP}.sqlite"
cp "$DB_SQLITE_PATH" "$OUT"
echo "Backup written to: $OUT"
