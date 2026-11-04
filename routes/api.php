<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AdminOptionController;
use App\Http\Controllers\Api\Admin\DepartmentController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Audit\AuditLogController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\It\ItOptionController;
use App\Http\Controllers\Api\It\ItDashboardController;
use App\Http\Controllers\Api\It\ItKnowledgeArticleController;
use App\Http\Controllers\Api\It\ItResponseTemplateController;
use App\Http\Controllers\Api\It\ItAutomationRuleController;
use App\Http\Controllers\Api\It\ItServiceCatalogController;
use App\Http\Controllers\Api\It\ItTicketApprovalController;
use App\Http\Controllers\Api\It\ItPreventiveMaintenanceController;
use App\Http\Controllers\Api\It\ItSlaCalendarController;
use App\Http\Controllers\Api\It\ItSlaRuleController;
use App\Http\Controllers\Api\It\ItSupportTeamController;
use App\Http\Controllers\Api\It\ItTicketCategoryController;
use App\Http\Controllers\Api\It\ItTicketController;
use App\Http\Controllers\Api\It\ItTicketEvaluationController;
use App\Http\Controllers\Api\It\ItTicketMessageController;
use App\Http\Controllers\Api\It\ItTicketToolController;
use App\Http\Controllers\Api\It\ItChecklistTemplateController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Materials\AssetController;
use App\Http\Controllers\Api\Materials\AssetMovementController;
use App\Http\Controllers\Api\Materials\MaterialOptionController;
use App\Http\Controllers\Api\Materials\ProductController;
use App\Http\Controllers\Api\Materials\StockMovementController;
use App\Http\Controllers\Api\Transport\DriverController;
use App\Http\Controllers\Api\Transport\VehicleMaintenanceController;
use App\Http\Controllers\Api\Transport\VehicleController;
use App\Http\Controllers\Api\Transport\VehicleRequestController;
use App\Http\Controllers\Api\Reports\ReportController;
use App\Http\Controllers\Api\Settings\SettingController;
use App\Http\Controllers\Api\Webhooks\WhatsappWebhookController;
use App\Http\Controllers\Api\It\WhatsappTemplateController;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/webhooks/whatsapp', [WhatsappWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsappWebhookController::class, 'receive']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/modules', [ModuleController::class, 'index']);
    Route::get('/dashboard', DashboardController::class)->middleware('permission:dashboard.visualizar');
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/search', GlobalSearchController::class);
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download']);
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy']);
    Route::get('/attachments/{type}/{id}', [AttachmentController::class, 'index'])->whereNumber('id');
    Route::post('/attachments/{type}/{id}', [AttachmentController::class, 'store'])->whereNumber('id');

    Route::prefix('admin')->middleware('permission:administracao.visualizar')->group(function () {
        Route::get('/options', AdminOptionController::class);
        Route::apiResource('users', UserController::class)->except(['show']);
        Route::apiResource('departments', DepartmentController::class)->except(['show']);
        Route::apiResource('roles', RoleController::class)->except(['show']);
    });

    Route::prefix('transport')->middleware('permission:transporte.visualizar')->group(function () {
        Route::get('/options', [VehicleRequestController::class, 'options']);
        Route::get('/availability', [VehicleRequestController::class, 'availability']);
        Route::get('/summary', [VehicleRequestController::class, 'summary']);
        Route::get('/agenda', [VehicleRequestController::class, 'agenda']);
        Route::get('/maintenances/summary', [VehicleMaintenanceController::class, 'summary']);
        Route::apiResource('maintenances', VehicleMaintenanceController::class)->except(['show']);
        Route::apiResource('vehicles', VehicleController::class)->except(['show']);
        Route::apiResource('drivers', DriverController::class)->except(['show']);
        Route::apiResource('requests', VehicleRequestController::class)
            ->parameters(['requests' => 'vehicleRequest'])
            ->except(['destroy']);
        Route::post('/requests/{vehicleRequest}/approve', [VehicleRequestController::class, 'approve'])->middleware('permission:transporte.aprovar');
        Route::post('/requests/{vehicleRequest}/cancel', [VehicleRequestController::class, 'cancel'])->middleware('permission:transporte.cancelar');
        Route::post('/requests/{vehicleRequest}/departure-checklist', [VehicleRequestController::class, 'departureChecklist'])->middleware('permission:transporte.aprovar');
        Route::post('/requests/{vehicleRequest}/return-checklist', [VehicleRequestController::class, 'returnChecklist'])->middleware('permission:transporte.aprovar');
        Route::post('/requests/{vehicleRequest}/status', [VehicleRequestController::class, 'changeStatus']);
    });

    Route::prefix('materials')->middleware('permission:materiais-patrimonio.visualizar')->group(function () {
        Route::get('/options', MaterialOptionController::class);
        Route::apiResource('products', ProductController::class)->except(['show']);
        Route::apiResource('stock-movements', StockMovementController::class)->only(['index', 'store']);
        Route::apiResource('assets', AssetController::class);
        Route::apiResource('asset-movements', AssetMovementController::class)->only(['index', 'store']);
    });

    Route::prefix('it')->middleware('permission:informatica.visualizar')->group(function () {
        Route::get('/options', ItOptionController::class);
        Route::apiResource('whatsapp-templates', WhatsappTemplateController::class)->parameters(['whatsapp-templates' => 'template'])->only(['index', 'store', 'update']);
        Route::apiResource('response-templates', ItResponseTemplateController::class)->parameters(['response-templates' => 'template'])->only(['index', 'store', 'update']);
        Route::apiResource('automations', ItAutomationRuleController::class)->parameters(['automations' => 'automationRule'])->only(['index', 'store', 'update']);
        Route::apiResource('service-catalog', ItServiceCatalogController::class)->parameters(['service-catalog' => 'serviceCatalog'])->only(['index', 'store', 'update']);
        Route::get('/preventive-maintenance/summary', [ItPreventiveMaintenanceController::class, 'summary']);
        Route::post('/preventive-maintenance/generate', [ItPreventiveMaintenanceController::class, 'generate']);
        Route::post('/preventive-maintenance/{preventiveMaintenance}/complete', [ItPreventiveMaintenanceController::class, 'complete']);
        Route::apiResource('preventive-maintenance', ItPreventiveMaintenanceController::class)
            ->parameters(['preventive-maintenance' => 'preventiveMaintenance'])
            ->only(['index', 'store', 'update']);
        Route::get('/approvals', [ItTicketApprovalController::class, 'index']);
        Route::post('/approvals/{approval}/decide', [ItTicketApprovalController::class, 'decide']);
        Route::apiResource('checklist-templates', ItChecklistTemplateController::class)->except(['show', 'destroy']);
        Route::get('/dashboard/operational', [ItDashboardController::class, 'operational']);
        Route::get('/dashboard/technician', [ItDashboardController::class, 'technician']);
        Route::get('/dashboard/recurrences', [ItDashboardController::class, 'recurrences']);
        Route::get('/knowledge/suggestions', [ItKnowledgeArticleController::class, 'suggestions']);
        Route::post('/knowledge/from-ticket/{ticket}', [ItKnowledgeArticleController::class, 'fromTicket']);
        Route::post('/knowledge/{article}/publish', [ItKnowledgeArticleController::class, 'publish']);
        Route::apiResource('knowledge', ItKnowledgeArticleController::class)
            ->parameters(['knowledge' => 'article'])
            ->except(['destroy']);
        Route::apiResource('support-teams', ItSupportTeamController::class)
            ->parameters(['support-teams' => 'supportTeam'])
            ->except(['show', 'destroy']);
        Route::get('/evaluations/stats', [ItTicketEvaluationController::class, 'stats']);
        Route::apiResource('sla-calendars', ItSlaCalendarController::class)
            ->parameters(['sla-calendars' => 'slaCalendar'])
            ->except(['show', 'destroy']);
        Route::apiResource('sla-rules', ItSlaRuleController::class)
            ->parameters(['sla-rules' => 'slaRule'])
            ->except(['show', 'destroy']);
        Route::apiResource('categories', ItTicketCategoryController::class)
            ->parameters(['categories' => 'category'])
            ->except(['show']);
        Route::get('/tickets/summary', [ItTicketController::class, 'summary']);
        Route::get('/tickets/{ticket}/assignment-recommendations', [ItTicketController::class, 'assignmentRecommendations']);
        Route::get('/tickets/{ticket}/timeline', [ItTicketController::class, 'timeline']);
        Route::apiResource('tickets', ItTicketController::class)
            ->parameters(['tickets' => 'ticket'])
            ->except(['destroy']);
        Route::post('/tickets/{ticket}/status', [ItTicketController::class, 'changeStatus']);
        Route::post('/tickets/{ticket}/cancel', [ItTicketController::class, 'cancel']);
        Route::post('/tickets/bulk-action', [ItTicketController::class, 'bulkAction']);
        Route::post('/tickets/{ticket}/messages', [ItTicketMessageController::class, 'store']);
        Route::post('/tickets/{ticket}/evaluation', [ItTicketEvaluationController::class, 'store']);
        Route::post('/tickets/{ticket}/followers', [ItTicketToolController::class, 'addFollower']);
        Route::delete('/tickets/{ticket}/followers/{userId}', [ItTicketToolController::class, 'removeFollower']);
        Route::post('/tickets/{ticket}/tasks', [ItTicketToolController::class, 'storeTask']);
        Route::patch('/tickets/{ticket}/tasks/{task}', [ItTicketToolController::class, 'updateTask']);
        Route::post('/tickets/{ticket}/checklist-items', [ItTicketToolController::class, 'storeChecklistItem']);
        Route::patch('/tickets/{ticket}/checklist-items/{item}', [ItTicketToolController::class, 'updateChecklistItem']);
        Route::post('/tickets/{ticket}/relationships', [ItTicketToolController::class, 'relate']);
        Route::post('/tickets/{ticket}/merge-duplicate', [ItTicketToolController::class, 'mergeDuplicate']);
        Route::post('/tickets/{ticket}/reflect-incident', [ItTicketToolController::class, 'reflectIncident']);
    });

    Route::prefix('audit')->middleware('permission:auditoria.visualizar')->group(function () {
        Route::get('/logs', [AuditLogController::class, 'index']);
        Route::get('/options', [AuditLogController::class, 'options']);
    });

    Route::prefix('reports')->middleware('permission:relatorios.visualizar')->group(function () {
        Route::get('/options', [ReportController::class, 'options']);
        Route::get('/preview', [ReportController::class, 'preview']);
        Route::get('/export', [ReportController::class, 'export']);
    });

    Route::prefix('settings')->middleware('permission:configuracoes.visualizar')->group(function () {
        Route::get('/', [SettingController::class, 'index']);
        Route::put('/', [SettingController::class, 'update'])->middleware('permission:configuracoes.editar');
    });

    Route::get('/user', fn (Request $request) => $request->user()->load('department'));
});
