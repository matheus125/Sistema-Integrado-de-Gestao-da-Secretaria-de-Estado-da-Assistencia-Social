<?php

declare(strict_types=1);
umask(0077);
$root = dirname(__DIR__);
$backup = trim(file_get_contents($root.'/backups/latest-mysql-migration'));
if (!is_file($root.'/storage/framework/down') || !is_file($backup.'/validation.json')) {
    throw new RuntimeException('Requires maintenance mode and a verified import.');
}
$password = getenv('SIGES_DB_PASSWORD');
if (!$password || strpbrk($password, "\r\n") !== false) throw new RuntimeException('Invalid password input');
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=siges;charset=utf8mb4','dev',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$report = json_decode(file_get_contents($backup.'/validation.json'),true,512,JSON_THROW_ON_ERROR);
foreach ($report['tables'] as $table=>$check) {
    if ((int)$pdo->query('SELECT COUNT(*) FROM `'.str_replace('`','``',$table).'`')->fetchColumn() !== $check['count']) throw new RuntimeException('Destination changed: '.$table);
}
$env = file_get_contents($root.'/.env');
$values = ['DB_CONNECTION'=>'mysql','DB_HOST'=>'127.0.0.1','DB_PORT'=>'3306','DB_DATABASE'=>'siges','DB_USERNAME'=>'dev',
    'DB_PASSWORD'=>'"'.str_replace(['\\','"','$'],['\\\\','\\"','\\$'],$password).'"', 'DB_URL'=>''];
foreach ($values as $key=>$value) {
    $pattern='/^(?:#\s*)?'.preg_quote($key,'/').'=.*$/m';
    if (preg_match($pattern,$env)) $env=preg_replace_callback($pattern,fn()=> $key.'='.$value,$env);
    else $env.="\n".$key.'='.$value."\n";
}
file_put_contents($root.'/.env.mysql-next',$env);
chmod($root.'/.env.mysql-next',0600);
rename($root.'/.env.mysql-next',$root.'/.env');
echo "MySQL configuration activated; APP_KEY preserved.\n";
