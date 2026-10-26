<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\FinancialTransaction;

return function (): void {
    echo "ReportsAndBackupTest\n";

    T::run('Workflow #23: livestock register report returns accurate, reconciling data', function () {
        $pdo = test_pdo();
        $totalAnimals = (int) $pdo->query('SELECT COUNT(*) FROM animals')->fetchColumn();
        $registerRows = Animal::search([], 1000);
        T::assertSame($totalAnimals, count($registerRows), 'the livestock register must list every animal row, matching the raw table count');
    });

    T::run('Income/expense report totals reconcile with the sum of underlying transactions', function () {
        $pdo = test_pdo();
        $from = '2000-01-01';
        $to = '2100-01-01';
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM financial_transactions WHERE type = 'income' AND txn_date BETWEEN :f AND :t");
        $stmt->execute(['f' => $from, 't' => $to]);
        $rawIncome = (float) $stmt->fetch()['t'];

        $summary = FinancialTransaction::summary($from, $to);
        T::assertEqualsFloat($rawIncome, $summary['income'], 'the report\'s income total must equal a direct SUM() over financial_transactions', 0.5);
    });

    T::run('Workflow #24: back up the SQLite test database and restore it into a clean database', function () {
        $sourcePath = __DIR__ . '/../storage/test_run.sqlite';
        $backupPath = __DIR__ . '/../storage/test_run_backup.sqlite';
        $restoredPath = __DIR__ . '/../storage/test_run_restored.sqlite';

        T::assertTrue(is_file($sourcePath), 'the live test database file must exist to back up');

        // SQLite is in WAL mode (see Database::connection()): recent
        // commits can sit in a separate -wal file rather than the main
        // .sqlite file, so a safe backup must checkpoint WAL into the
        // main file FIRST, or a plain file copy can silently miss
        // committed data (exactly what this test caught -- see
        // scripts/backup-sqlite.sh, which does the same checkpoint).
        test_pdo()->exec('PRAGMA wal_checkpoint(FULL)');

        // scripts/backup-sqlite.sh / restore-sqlite.sh wrap exactly this
        // "copy the file" procedure for a real SQLite deployment; for
        // MySQL (the production target) the equivalent is mysqldump /
        // mysql, documented in docs/BACKUP_RESTORE.md and NOT re-tested
        // here since no MySQL server is available in this sandbox (see
        // docs/FINAL_ACCEPTANCE_REPORT.md).
        copy($sourcePath, $backupPath);
        T::assertTrue(is_file($backupPath), 'backup file must be created');

        copy($backupPath, $restoredPath);
        $restored = new \PDO('sqlite:' . $restoredPath);
        $animalCountOriginal = (int) (new \PDO('sqlite:' . $sourcePath))->query('SELECT COUNT(*) FROM animals')->fetchColumn();
        $animalCountRestored = (int) $restored->query('SELECT COUNT(*) FROM animals')->fetchColumn();

        T::assertSame($animalCountOriginal, $animalCountRestored, 'restored database must contain the same number of animal records as the original');

        unlink($backupPath);
        unlink($restoredPath);
    });
};
