<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItTicket;
use App\Models\Product;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $departmentId = $user->hasRole('Administrador Geral') ? null : $user->department_id;
        $notifications = collect();

        if ($user->can('materiais-patrimonio.visualizar')) {
            Product::query()
                ->whereColumn('quantity', '<=', 'minimum_quantity')
                ->orderBy('quantity')
                ->limit(5)
                ->get(['id', 'internal_code', 'description', 'quantity', 'minimum_quantity', 'unit'])
                ->each(fn (Product $product) => $notifications->push([
                    'id' => "product-low-stock-{$product->id}",
                    'module' => 'materiais',
                    'title' => 'Produto abaixo do estoque minimo',
                    'detail' => "{$product->internal_code} - {$product->description}: {$product->quantity} {$product->unit}",
                    'icon' => 'inventory',
                    'color' => 'negative',
                    'severity' => 'critical',
                    'to' => '/materiais',
                    'created_at' => now()->toISOString(),
                ]));
        }

        if ($user->can('transporte.visualizar')) {
            Vehicle::query()
                ->whereNotNull('next_licensing_at')
                ->whereDate('next_licensing_at', '<=', now()->addDays(30))
                ->orderBy('next_licensing_at')
                ->limit(5)
                ->get(['id', 'plate', 'brand', 'model', 'next_licensing_at'])
                ->each(fn (Vehicle $vehicle) => $notifications->push([
                    'id' => "vehicle-license-{$vehicle->id}",
                    'module' => 'transporte',
                    'title' => 'Licenciamento proximo do vencimento',
                    'detail' => "{$vehicle->plate} - {$vehicle->brand} {$vehicle->model}: {$vehicle->next_licensing_at?->format('d/m/Y')}",
                    'icon' => 'event_busy',
                    'color' => 'warning',
                    'severity' => 'warning',
                    'to' => '/transporte',
                    'created_at' => optional($vehicle->next_licensing_at)->toISOString(),
                ]));

            VehicleRequest::query()
                ->with('department:id,name')
                ->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId))
                ->whereIn('status', ['solicitacao', 'analise'])
                ->latest()
                ->limit(5)
                ->get(['id', 'department_id', 'status', 'origin', 'destination', 'date'])
                ->each(fn (VehicleRequest $request) => $notifications->push([
                    'id' => "vehicle-request-{$request->id}",
                    'module' => 'transporte',
                    'title' => 'Solicitacao de veiculo aguardando analise',
                    'detail' => "{$request->department?->name}: {$request->origin} para {$request->destination}",
                    'icon' => 'route',
                    'color' => 'primary',
                    'severity' => 'info',
                    'to' => '/transporte',
                    'created_at' => $request->date?->toISOString(),
                ]));
        }

        if ($user->can('informatica.visualizar')) {
            ItTicket::query()
                ->with('department:id,name')
                ->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId))
                ->whereNotIn('status', ['resolvido', 'encerrado'])
                ->whereNotNull('sla_due_at')
                ->where('sla_due_at', '<=', now()->addDay())
                ->orderBy('sla_due_at')
                ->limit(5)
                ->get(['id', 'department_id', 'requester_name', 'category', 'priority', 'sla_due_at'])
                ->each(fn (ItTicket $ticket) => $notifications->push([
                    'id' => "it-ticket-sla-{$ticket->id}",
                    'module' => 'informatica',
                    'title' => 'SLA de chamado em risco',
                    'detail' => "#{$ticket->id} - {$ticket->requester_name}: vence em {$ticket->sla_due_at?->format('d/m/Y H:i')}",
                    'icon' => 'support_agent',
                    'color' => $ticket->sla_due_at?->isPast() ? 'negative' : 'warning',
                    'severity' => $ticket->sla_due_at?->isPast() ? 'critical' : 'warning',
                    'to' => '/informatica',
                    'created_at' => $ticket->sla_due_at?->toISOString(),
                ]));

            ItTicket::query()
                ->with('department:id,name')
                ->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId))
                ->where('status', 'aberto')
                ->latest()
                ->limit(5)
                ->get(['id', 'department_id', 'requester_name', 'category', 'created_at'])
                ->each(fn (ItTicket $ticket) => $notifications->push([
                    'id' => "it-ticket-open-{$ticket->id}",
                    'module' => 'informatica',
                    'title' => 'Novo chamado aguardando triagem',
                    'detail' => "#{$ticket->id} - {$ticket->requester_name}: {$ticket->category}",
                    'icon' => 'support_agent',
                    'color' => 'primary',
                    'severity' => 'info',
                    'to' => '/informatica',
                    'created_at' => $ticket->created_at?->toISOString(),
                ]));
        }

        $data = $notifications
            ->sortBy(fn (array $item) => ['critical' => 0, 'warning' => 1, 'info' => 2][$item['severity']] ?? 3)
            ->values()
            ->take(20);

        return response()->json([
            'data' => $data,
            'unread_count' => $data->count(),
            'generated_at' => now()->toISOString(),
        ]);
    }
}
