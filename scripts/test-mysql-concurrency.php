<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$config = config('database.connections.mysql');
$database = 'siges_fix013_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(3));

if (! preg_match('/^siges_fix013_[A-Za-z0-9_]+$/', $database)) {
    throw new RuntimeException('Unsafe disposable database name.');
}

$dsn = "mysql:host={$config['host']};port={$config['port']};charset={$config['charset']}";
$pdo = new PDO($dsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$environment = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'mysql',
    'DB_DATABASE' => $database,
    'DB_URL' => '',
];

try {
    $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    fwrite(STDOUT, "Banco MySQL descartável criado: {$database}\n");

    runCommand([PHP_BINARY, 'artisan', 'migrate:fresh', '--force', '--no-interaction'], $root, $environment);
    runCommand([PHP_BINARY, 'artisan', 'test', 'tests/Integration/MysqlConcurrencyTest.php'], $root, $environment);
} finally {
    $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
    fwrite(STDOUT, "Banco MySQL descartável removido: {$database}\n");
}

/**
 * @param  list<string>  $command
 * @param  array<string, string>  $environment
 */
function runCommand(array $command, string $workingDirectory, array $environment): void
{
    $process = new Process($command, $workingDirectory, $environment);
    $process->setTimeout(120);
    $process->run(static function (string $type, string $output): void {
        fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
    });

    if (! $process->isSuccessful()) {
        throw new ProcessFailedException($process);
    }
}
