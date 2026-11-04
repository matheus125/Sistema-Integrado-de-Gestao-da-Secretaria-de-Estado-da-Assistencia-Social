<?php

declare(strict_types=1);
$root=dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default')!=='mysql') throw new RuntimeException('MySQL is not the default');
$db=Illuminate\Support\Facades\DB::connection();
if ($db->getDatabaseName()!=='siges') throw new RuntimeException('Unexpected database');
$user=App\Models\User::role('Administrador Geral')->firstOrFail();
$request=Illuminate\Http\Request::create('/','GET');
$request->setUserResolver(fn()=>$user);
$checks = [
    'dashboard' => app(App\Http\Controllers\Api\DashboardController::class)->__invoke($request),
    'it_dashboard' => app(App\Http\Controllers\Api\It\ItDashboardController::class)->operational($request, app(App\Services\It\ItMetricsService::class)),
    'recurrences' => app(App\Http\Controllers\Api\It\ItDashboardController::class)->recurrences($request),
    'evaluations' => app(App\Http\Controllers\Api\It\ItTicketEvaluationController::class)->stats($request),
    'maintenance' => app(App\Http\Controllers\Api\Transport\VehicleMaintenanceController::class)->summary($request),
];
foreach ($checks as $name=>$response) {
    if ($response->getStatusCode()!==200) throw new RuntimeException('Failed: '.$name);
    echo $name.": OK\n";
}
foreach ($db->select("SELECT TABLE_NAME,AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA='siges' AND AUTO_INCREMENT IS NOT NULL") as $table) {
    if ($table->AUTO_INCREMENT <= (int)$db->table($table->TABLE_NAME)->max('id')) throw new RuntimeException('Invalid auto increment: '.$table->TABLE_NAME);
}
echo 'MySQL active; users='.App\Models\User::count().'; tickets='.App\Models\ItTicket::withTrashed()->count()."; auto increments OK.\n";
