<?php

// Call only after draining HTTP writes and stopping workers/scheduler.
declare(strict_types=1);

umask(0077);

require dirname(__DIR__).'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

$db = null;
$restoreDatabase = null;
$exitCode = 0;
$cleanupRestore = in_array('--cleanup-restore', $argv, true);
$isolatedTest = in_array('--isolated-test', $argv, true);
$verify = in_array('--verify', $argv, true);

try {
    $root = dirname(__DIR__);

    if ($isolatedTest && (! $verify || ! $cleanupRestore)) {
        throw new RuntimeException('--isolated-test requires --verify and --cleanup-restore.');
    }

    if (! $isolatedTest && (! in_array('--quiesced', $argv, true) || ! is_file($root.'/storage/framework/down'))) {
        throw new RuntimeException('Requires maintenance mode, stopped writers and --quiesced.');
    }

    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    if (config('database.default') !== 'mysql') {
        throw new RuntimeException('MySQL required');
    }

    $db = DB::connection();
    $cfg = $db->getConfig();
    $sourceDatabase = (string) $cfg['database'];

    if ($isolatedTest && (app()->environment() !== 'testing' || ! str_starts_with($sourceDatabase, 'siges_fix008_'))) {
        throw new RuntimeException('Isolated test mode requires APP_ENV=testing and a siges_fix008_* database.');
    }

    $directory = (getenv('SIGES_BACKUP_ROOT') ?: $root.'/backups').'/mysql-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(3));

    if (! mkdir($directory, 0700, true)) {
        throw new RuntimeException('Cannot create backup directory');
    }

    $connection = [
        '--host='.$cfg['host'],
        '--port='.$cfg['port'],
        '--user='.$cfg['username'],
        '--default-character-set=utf8mb4',
    ];

    if (! empty($cfg['unix_socket'])) {
        $connection[] = '--socket='.$cfg['unix_socket'];
    } else {
        $connection[] = '--protocol=TCP';
    }

    $environment = ['MYSQL_PWD' => $cfg['password']];
    $quoteIdentifier = static fn (string $identifier): string => '`'.str_replace('`', '``', $identifier).'`';
    $fingerprint = static function (array $rows): string {
        $normalized = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            ksort($row);

            foreach ($row as &$value) {
                if ($value !== null) {
                    $value = (string) $value;
                }
            }

            unset($value);
            $normalized[] = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        sort($normalized);

        return hash('sha256', implode("\n", $normalized));
    };
    $foreignKeys = static function (ConnectionInterface $connection, string $schema): array {
        $rows = $connection->select(
            <<<'SQL'
                SELECT
                    k.CONSTRAINT_NAME,
                    k.TABLE_NAME,
                    k.COLUMN_NAME,
                    k.ORDINAL_POSITION,
                    k.REFERENCED_TABLE_NAME,
                    k.REFERENCED_COLUMN_NAME,
                    r.UPDATE_RULE,
                    r.DELETE_RULE
                FROM information_schema.KEY_COLUMN_USAGE k
                INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                    ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
                    AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                    AND r.TABLE_NAME = k.TABLE_NAME
                WHERE k.TABLE_SCHEMA = ?
                    AND k.REFERENCED_TABLE_NAME IS NOT NULL
                ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION
                SQL,
            [$schema],
        );

        return array_map(static function (object $row): array {
            return array_map(
                static fn (mixed $value): string => (string) $value,
                (array) $row,
            );
        }, $rows);
    };
    $checkTables = static function (
        ConnectionInterface $connection,
        string $schema,
        array $tables,
        callable $quote,
    ): void {
        foreach ($tables as $table) {
            $results = $connection->select(
                'CHECK TABLE '.$quote($schema).'.'.$quote($table).' QUICK',
            );
            $status = end($results);

            if (! $status || strtolower((string) $status->Msg_type) !== 'status' || strtolower((string) $status->Msg_text) !== 'ok') {
                throw new RuntimeException('CHECK TABLE failed: '.$table);
            }
        }
    };

    $tables = $db->select(
        "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
        [$sourceDatabase],
    );
    $manifest = [
        'database' => $sourceDatabase,
        'created_at' => gmdate('c'),
        'tables' => [],
        'files' => [],
    ];

    foreach ($tables as $table) {
        if ($table->ENGINE !== 'InnoDB') {
            throw new RuntimeException('Non-InnoDB table: '.$table->TABLE_NAME);
        }

        $rows = $db->table($table->TABLE_NAME)->get()->all();
        $manifest['tables'][$table->TABLE_NAME] = [
            'count' => count($rows),
            'sha256' => $fingerprint($rows),
        ];
    }

    $sourceForeignKeys = $foreignKeys($db, $sourceDatabase);
    $migrationFiles = $app->make('migrator')->getMigrationFiles($root.'/database/migrations');
    $expectedMigrations = array_keys($migrationFiles);
    $sourceMigrations = $db->table('migrations')->orderBy('migration')->pluck('migration')->all();
    $pendingMigrations = array_values(array_diff($expectedMigrations, $sourceMigrations));

    if ($isolatedTest && $pendingMigrations !== []) {
        throw new RuntimeException('Source database has pending migrations: '.implode(', ', $pendingMigrations));
    }

    $dump = new Process([
        'mysqldump',
        ...$connection,
        '--single-transaction',
        '--quick',
        '--no-tablespaces',
        '--set-gtid-purged=OFF',
        '--hex-blob',
        '--result-file='.$directory.'/database.sql',
        $sourceDatabase,
    ], $root, $environment);
    $dump->setTimeout(1800);
    $dump->mustRun();

    if ($isolatedTest) {
        file_put_contents($directory.'/environment.json', json_encode([
            'app_env' => 'testing',
            'database' => $sourceDatabase,
            'credentials_included' => false,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    } else {
        copy($root.'/.env', $directory.'/env');
        chmod($directory.'/env', 0600);
    }

    if (! $isolatedTest) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/storage/app', FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $manifest['files'][substr($file->getPathname(), strlen($root) + 1)] = hash_file('sha256', $file->getPathname());
            }
        }
    }

    $tarArguments = $isolatedTest
        ? ['tar', '-czf', $directory.'/attachments.tar.gz', '--files-from=/dev/null']
        : ['tar', '-czf', $directory.'/attachments.tar.gz', '-C', $root, 'storage/app'];
    $tar = new Process($tarArguments);
    $tar->setTimeout(1800);
    $tar->mustRun();

    $manifest['sql_sha256'] = hash_file('sha256', $directory.'/database.sql');
    $manifest['archive_sha256'] = hash_file('sha256', $directory.'/attachments.tar.gz');
    $manifest['foreign_keys'] = count($sourceForeignKeys);
    $manifest['migrations'] = count($sourceMigrations);
    file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    if ($verify) {
        $restoreDatabase = 'siges_restore_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(3));
        $db->statement('CREATE DATABASE '.$quoteIdentifier($restoreDatabase).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        $input = fopen($directory.'/database.sql', 'rb');

        if ($input === false) {
            throw new RuntimeException('Cannot read generated SQL dump');
        }

        try {
            $process = new Process(['mysql', ...$connection, $restoreDatabase], $root, $environment);
            $process->setInput($input);
            $process->setTimeout(1800);
            $process->mustRun();
        } finally {
            fclose($input);
        }

        $totalRecords = 0;

        foreach ($manifest['tables'] as $table => $expected) {
            $rows = $db->table($restoreDatabase.'.'.$table)->get()->all();

            if (count($rows) !== $expected['count'] || $fingerprint($rows) !== $expected['sha256']) {
                throw new RuntimeException('Restoration mismatch: '.$table);
            }

            $totalRecords += count($rows);
        }

        $restoredForeignKeys = $foreignKeys($db, $restoreDatabase);

        if ($restoredForeignKeys !== $sourceForeignKeys) {
            throw new RuntimeException('Restored foreign keys do not match the source database');
        }

        $restoredMigrations = $db->table($restoreDatabase.'.migrations')->orderBy('migration')->pluck('migration')->all();

        if ($restoredMigrations !== $sourceMigrations) {
            throw new RuntimeException('Restored migration history is incomplete or inconsistent');
        }

        $restoredPendingMigrations = array_values(array_diff($expectedMigrations, $restoredMigrations));

        if ($isolatedTest && $restoredPendingMigrations !== []) {
            throw new RuntimeException('Restored test database has pending migrations');
        }

        $checkTables($db, $restoreDatabase, array_keys($manifest['tables']), $quoteIdentifier);

        $extracted = $directory.'/restore-files';

        if (! mkdir($extracted, 0700)) {
            throw new RuntimeException('Cannot create attachment restore directory');
        }

        (new Process(['tar', '-xzf', $directory.'/attachments.tar.gz', '-C', $extracted]))->mustRun();

        foreach ($manifest['files'] as $file => $hash) {
            if (! is_file($extracted.'/'.$file) || hash_file('sha256', $extracted.'/'.$file) !== $hash) {
                throw new RuntimeException('Restored attachment mismatch: '.$file);
            }
        }

        $validation = [
            'source_database' => $sourceDatabase,
            'restored_database' => $restoreDatabase,
            'tables' => count($manifest['tables']),
            'records' => $totalRecords,
            'foreign_keys' => count($restoredForeignKeys),
            'migrations' => count($restoredMigrations),
            'pending_migrations' => count($restoredPendingMigrations),
            'check_table' => 'ok',
            'files' => count($manifest['files']),
            'cleanup_requested' => $cleanupRestore,
            'verified_at' => gmdate('c'),
        ];
        file_put_contents($directory.'/restore-validation.json', json_encode($validation, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        echo "Restoration verified in isolated database: $restoreDatabase\n";
    }

    file_put_contents($directory.'/COMPLETE', gmdate('c')."\n");
    echo "Backup complete: $directory\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Backup failed: '.$error->getMessage()."\n");
    $exitCode = 1;
} finally {
    if ($cleanupRestore && $db instanceof ConnectionInterface && is_string($restoreDatabase)) {
        try {
            $quotedRestore = '`'.str_replace('`', '``', $restoreDatabase).'`';
            $db->statement('DROP DATABASE '.$quotedRestore);
            echo "Disposable restore database removed: $restoreDatabase\n";
        } catch (Throwable $cleanupError) {
            fwrite(STDERR, 'Failed to remove disposable restore database: '.$cleanupError->getMessage()."\n");
            $exitCode = 1;
        }
    }
}

exit($exitCode);
