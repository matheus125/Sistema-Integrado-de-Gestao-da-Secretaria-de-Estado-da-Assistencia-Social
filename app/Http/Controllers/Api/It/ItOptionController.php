<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Asset;
use App\Models\ItTicket;
use App\Models\ItTicketCategory;
use App\Models\ItRootCause;
use App\Models\ItSupportTeam;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItOptionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $itDepartmentId = Department::query()->where('slug', 'gerencia-de-informatica')->value('id');
        $fullAccess = $request->user()->hasRole('Administrador Geral') || $request->user()->can('informatica.aprovar');

        return response()->json([
            'departments' => Department::query()
                ->where('active', true)
                ->when(! $fullAccess, fn ($query) => $query->whereKey($request->user()->department_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'technicians' => User::query()
                ->where('status', 'ativo')
                ->when($itDepartmentId, fn ($query) => $query->where('department_id', $itDepartmentId))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'categories' => ItTicketCategory::query()
                ->with('parent:id,name')
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'parent_id', 'name', 'color', 'icon', 'sort_order']),
            'types' => collect(ItTicket::TYPES)
                ->map(fn (array $meta, string $value) => ['value' => $value, ...$meta])
                ->values(),
            'assets' => Asset::query()
                ->with(['department:id,name', 'responsible:id,name'])
                ->when(! $fullAccess, fn ($query) => $query->where('department_id', $request->user()->department_id))
                ->orderBy('asset_number')
                ->limit(200)
                ->get(['id', 'department_id', 'responsible_id', 'asset_number', 'description', 'brand', 'model', 'serial_number', 'status']),
            'root_causes' => ItRootCause::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'support_teams' => ItSupportTeam::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
