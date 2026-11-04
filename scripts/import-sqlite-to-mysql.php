<?php

// One-time import into the freshly migrated local siges database.
// Password is supplied only through SIGES_DB_PASSWORD; no Laravel events run.
declare(strict_types=1);
umask(0077);
$root = dirname(__DIR__);
$backup = trim(file_get_contents($root.'/backups/latest-mysql-migration'));
if (($argv[1] ?? '') !== '--execute' || !is_file($root.'/storage/framework/down')) {
    throw new RuntimeException('Requires --execute and application maintenance mode.');
}
$source = new PDO('sqlite:file:'.$backup.'/database.sqlite?mode=ro', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$source->exec('PRAGMA query_only=ON');
$mysql = new PDO('mysql:host=127.0.0.1;port=3306;dbname=siges;charset=utf8mb4', 'dev', getenv('SIGES_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$mysql->exec("SET time_zone='+00:00'");
$mysql->exec("SET SESSION sql_mode='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
$quote = fn ($name) => '`'.str_replace('`', '``', $name).'`';
$tables = $source->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$destTables = $mysql->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
sort($destTables);
if ($tables !== $destTables || $source->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $source->query('PRAGMA foreign_key_check')->fetchAll()) {
    throw new RuntimeException('Schema or SQLite integrity mismatch.');
}
$applied = $source->query('SELECT migration FROM migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN);
if ($applied !== $mysql->query('SELECT migration FROM migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN)) {
    throw new RuntimeException('Migration history mismatch.');
}
$initialCounts = ['migrations'=>24, 'it_sla_calendars'=>1, 'it_sla_rules'=>5, 'it_root_causes'=>11, 'it_support_teams'=>7, 'it_escalation_rules'=>4, 'whatsapp_message_templates'=>2];
$before = []; $rows = []; $types = [];
foreach ($tables as $table) {
    $before[$table] = $mysql->query('SELECT * FROM '.$quote($table))->fetchAll(PDO::FETCH_ASSOC);
    if (count($before[$table]) !== ($initialCounts[$table] ?? 0)) {
        throw new RuntimeException('Destination is not freshly migrated: '.$table);
    }
    $rows[$table] = $source->query('SELECT * FROM '.$quote($table))->fetchAll(PDO::FETCH_ASSOC);
    $columns = $mysql->query('SHOW COLUMNS FROM '.$quote($table))->fetchAll(PDO::FETCH_ASSOC);
    $types[$table] = array_column($columns, 'Type', 'Field');
    $sourceColumns = array_column($source->query('PRAGMA table_info('.$quote($table).')')->fetchAll(PDO::FETCH_ASSOC), 'name');
    $targetColumns = array_keys($types[$table]); sort($sourceColumns); sort($targetColumns);
    if ($sourceColumns !== $targetColumns) throw new RuntimeException('Column mismatch: '.$table);
}
file_put_contents($backup.'/mysql-before.json', json_encode($before, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
function canonicalJson(mixed $value): mixed {
    if ($value instanceof stdClass) {
        $items = get_object_vars($value); ksort($items);
        foreach ($items as &$item) $item = canonicalJson($item);
        return (object) $items;
    }
    return is_array($value) ? array_map('canonicalJson', $value) : $value;
}
function fingerprint(array $rows, array $types): string {
    $normalized = [];
    foreach ($rows as $row) {
        ksort($row);
        foreach ($row as $column => &$value) {
            if ($value === null) continue;
            $type = $types[$column];
            if ($type === 'json') $value = canonicalJson(json_decode((string) $value, false, 512, JSON_THROW_ON_ERROR));
            elseif (preg_match('/^decimal\(\d+,(\d+)\)/', $type, $match)) $value = bcadd((string) $value, '0', (int) $match[1]);
            elseif (str_starts_with($type, 'time') && strlen((string) $value) === 5) $value .= ':00';
            else $value = (string) $value;
        }
        unset($value);
        $normalized[] = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
    sort($normalized);
    return hash('sha256', implode("\n", $normalized));
}
$report = ['tables'=>[], 'foreign_keys_checked'=>0, 'excluded'=>['migrations'=>'Retained MySQL migration execution history', 'cache'=>'Rebuilt after cutover', 'cache_locks'=>'Transient locks not imported']];
$mysql->exec('SET FOREIGN_KEY_CHECKS=0');
$mysql->beginTransaction();
try {
    foreach ($tables as $table) {
        if ($table === 'migrations') continue;
        $mysql->exec('DELETE FROM '.$quote($table));
        if (in_array($table, ['cache','cache_locks'], true)) continue;
        foreach ($rows[$table] as $row) {
            $columns = array_keys($row);
            $statement = $mysql->prepare('INSERT INTO '.$quote($table).' ('.implode(',',array_map($quote,$columns)).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')');
            $statement->execute(array_values($row));
        }
        $actual = $mysql->query('SELECT * FROM '.$quote($table))->fetchAll(PDO::FETCH_ASSOC);
        $expectedHash = fingerprint($rows[$table], $types[$table]);
        if (count($actual) !== count($rows[$table]) || fingerprint($actual,$types[$table]) !== $expectedHash) throw new RuntimeException('Data mismatch: '.$table);
        $report['tables'][$table] = ['count'=>count($actual), 'sha256'=>$expectedHash];
    }
    $foreignKeys = $mysql->query("SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='siges' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($foreignKeys as $fk) {
        $child=$quote($fk['TABLE_NAME']); $parent=$quote($fk['REFERENCED_TABLE_NAME']); $col=$quote($fk['COLUMN_NAME']); $ref=$quote($fk['REFERENCED_COLUMN_NAME']);
        if ($mysql->query("SELECT COUNT(*) FROM $child c LEFT JOIN $parent p ON c.$col=p.$ref WHERE c.$col IS NOT NULL AND p.$ref IS NULL")->fetchColumn() != 0) throw new RuntimeException('Orphan: '.$fk['TABLE_NAME'].'.'.$fk['COLUMN_NAME']);
        $report['foreign_keys_checked']++;
    }
    foreach ($source->query('SELECT disk,path,size FROM attachments')->fetchAll(PDO::FETCH_ASSOC) as $attachment) {
        $disk = ['local'=>'private','public'=>'public'][$attachment['disk']] ?? throw new RuntimeException('Unsupported attachment disk');
        $path=$root.'/storage/app/'.$disk.'/'.$attachment['path'];
        $manifest=json_decode(file_get_contents($backup.'/attachments-manifest.json'),true,512,JSON_THROW_ON_ERROR);
        $relative='storage/app/'.$disk.'/'.$attachment['path'];
        if (!is_file($path) || filesize($path)!=(int)$attachment['size'] || hash_file('sha256',$path)!==($manifest[$relative]??null)) throw new RuntimeException('Attachment mismatch');
    }
    $mysql->commit();
} catch (Throwable $error) {
    if ($mysql->inTransaction()) $mysql->rollBack();
    throw $error;
} finally {
    $mysql->exec('SET FOREIGN_KEY_CHECKS=1');
}
$report['imported_rows']=array_sum(array_column($report['tables'],'count'));
$report['completed_at']=gmdate('c');
file_put_contents($backup.'/validation.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo 'Import verified: '.$report['imported_rows'].' rows, '.count($report['tables']).' tables, '.$report['foreign_keys_checked']." foreign keys.\n";
