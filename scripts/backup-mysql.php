<?php

// Call only after draining HTTP writes and stopping workers/scheduler.
declare(strict_types=1);
umask(0077);
require dirname(__DIR__).'/vendor/autoload.php';

use Symfony\Component\Process\Process;

try {
    $root=dirname(__DIR__);
    if (!in_array('--quiesced', $argv, true) || !is_file($root.'/storage/framework/down')) {
        throw new RuntimeException('Requires maintenance mode, stopped writers and --quiesced.');
    }
    $app=require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (config('database.default')!=='mysql') throw new RuntimeException('MySQL required');
    $db=Illuminate\Support\Facades\DB::connection();
    $cfg=$db->getConfig();
    $directory=(getenv('SIGES_BACKUP_ROOT') ?: $root.'/backups').'/mysql-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(3));
    if (!mkdir($directory,0700,true)) throw new RuntimeException('Cannot create backup directory');
    $connection=['--host='.$cfg['host'],'--port='.$cfg['port'],'--user='.$cfg['username'],'--default-character-set=utf8mb4'];
    if (!empty($cfg['unix_socket'])) $connection[]='--socket='.$cfg['unix_socket'];
    else $connection[]='--protocol=TCP';
    $environment=['MYSQL_PWD'=>$cfg['password']];
    $tables=$db->select("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE'",[$cfg['database']]);
    $manifest=['database'=>$cfg['database'],'created_at'=>gmdate('c'),'tables'=>[],'files'=>[]];
    $fingerprint=static function(array $rows): string {
        $normalized=[];
        foreach ($rows as $row) {
            $row=(array)$row; ksort($row);
            foreach ($row as &$value) if ($value!==null) $value=(string)$value;
            unset($value);
            $normalized[]=json_encode($row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        }
        sort($normalized);
        return hash('sha256',implode("\n",$normalized));
    };
    foreach ($tables as $table) {
        if ($table->ENGINE!=='InnoDB') throw new RuntimeException('Non-InnoDB table');
        $rows=$db->table($table->TABLE_NAME)->get()->all();
        $manifest['tables'][$table->TABLE_NAME]=['count'=>count($rows),'sha256'=>$fingerprint($rows)];
    }
    $dump=new Process(['mysqldump',...$connection,'--single-transaction','--quick','--no-tablespaces','--set-gtid-purged=OFF','--hex-blob','--result-file='.$directory.'/database.sql',$cfg['database']],$root,$environment);
    $dump->setTimeout(1800); $dump->mustRun();
    copy($root.'/.env',$directory.'/env'); chmod($directory.'/env',0600);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/storage/app',FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) $manifest['files'][substr($file->getPathname(),strlen($root)+1)]=hash_file('sha256',$file->getPathname());
    }
    $tar=new Process(['tar','-czf',$directory.'/attachments.tar.gz','-C',$root,'storage/app']);
    $tar->setTimeout(1800); $tar->mustRun();
    $manifest['sql_sha256']=hash_file('sha256',$directory.'/database.sql');
    $manifest['archive_sha256']=hash_file('sha256',$directory.'/attachments.tar.gz');
    file_put_contents($directory.'/manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    if (in_array('--verify', $argv, true)) {
        $restore='siges_restore_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(3));
        $db->statement("CREATE DATABASE `$restore` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $input=fopen($directory.'/database.sql','rb');
        $process=new Process(['mysql',...$connection,$restore],$root,$environment);
        $process->setInput($input); $process->setTimeout(1800); $process->mustRun(); fclose($input);
        foreach ($manifest['tables'] as $table=>$expected) {
            $rows=$db->table($restore.'.'.$table)->get()->all();
            if (count($rows)!==$expected['count'] || $fingerprint($rows)!==$expected['sha256']) throw new RuntimeException('Restoration mismatch: '.$table);
        }
        $extracted=$directory.'/restore-files'; mkdir($extracted,0700);
        (new Process(['tar','-xzf',$directory.'/attachments.tar.gz','-C',$extracted]))->mustRun();
        foreach ($manifest['files'] as $file=>$hash) {
            if (!is_file($extracted.'/'.$file) || hash_file('sha256',$extracted.'/'.$file)!==$hash) throw new RuntimeException('Restored attachment mismatch');
        }
        file_put_contents($directory.'/restore-validation.json',json_encode(['database'=>$restore,'tables'=>count($manifest['tables']),'files'=>count($manifest['files']),'verified_at'=>gmdate('c')],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        echo "Restoration verified in isolated database: $restore\n";
    }
    file_put_contents($directory.'/COMPLETE',gmdate('c')."\n");
    echo "Backup complete: $directory\n";
} catch (Throwable $error) {
    fwrite(STDERR,'Backup failed: '.$error->getMessage()."\n");
    exit(1);
}
