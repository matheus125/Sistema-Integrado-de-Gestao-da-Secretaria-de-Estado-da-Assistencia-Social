<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $term = '%'.$data['q'].'%';
        $user = $request->user();
        $departmentId = $user->department_id;
        $isAdmin = $user->hasRole('Administrador Geral');

        $results = [];

        if ($user->can('transporte.visualizar')) {
            $results['vehicles'] = DB::table('vehicles')
                ->select('id', 'plate as title', 'model as subtitle')
                ->whereNull('deleted_at')
                ->where(fn ($query) => $query->where('plate', 'like', $term)->orWhere('model', 'like', $term))
                ->limit(5)
                ->get();

            $results['drivers'] = DB::table('drivers')
                ->select('id', 'name as title', 'cnh as subtitle')
                ->whereNull('deleted_at')
                ->where(fn ($query) => $query->where('name', 'like', $term)->orWhere('cnh', 'like', $term))
                ->limit(5)
                ->get();
        }

        if ($user->can('materiais-patrimonio.visualizar')) {
            $results['products'] = DB::table('products')
                ->select('id', 'description as title', 'internal_code as subtitle')
                ->whereNull('deleted_at')
                ->where(fn ($query) => $query->where('description', 'like', $term)->orWhere('internal_code', 'like', $term))
                ->limit(5)
                ->get();

            $results['assets'] = DB::table('assets')
                ->select('id', 'description as title', 'asset_number as subtitle')
                ->whereNull('deleted_at')
                ->when(! $isAdmin && ! $user->can('materiais-patrimonio.aprovar'), fn ($query) => $query->where('department_id', $departmentId))
                ->where(fn ($query) => $query->where('description', 'like', $term)->orWhere('asset_number', 'like', $term))
                ->limit(5)
                ->get();
        }

        if ($user->can('informatica.visualizar')) {
            $results['tickets'] = DB::table('it_tickets')
                ->leftJoin('it_ticket_categories', 'it_ticket_categories.id', '=', 'it_tickets.category_id')
                ->leftJoin('assets', 'assets.id', '=', 'it_tickets.primary_asset_id')
                ->leftJoin('users as ticket_technicians', 'ticket_technicians.id', '=', 'it_tickets.assigned_to')
                ->leftJoin('departments as ticket_departments', 'ticket_departments.id', '=', 'it_tickets.department_id')
                ->select('it_tickets.id', 'it_tickets.requester_name as title', 'it_tickets.status as subtitle', 'it_tickets.description', 'it_ticket_categories.name as category', 'assets.asset_number', 'ticket_technicians.name as technician', 'ticket_departments.name as department')
                ->whereNull('it_tickets.deleted_at')
                ->when(! $isAdmin && ! $user->can('informatica.aprovar'), function ($query) use ($user, $departmentId) {
                    $query->where(function ($query) use ($user, $departmentId) {
                        $query->where('it_tickets.assigned_to', $user->id)
                            ->orWhere('it_tickets.opened_by', $user->id)
                            ->orWhere('it_tickets.department_id', $departmentId);
                    });
                })
                ->where(function ($query) use ($term, $data) {
                    $query->where('it_tickets.id', is_numeric($data['q']) ? (int) $data['q'] : 0)
                        ->orWhere('it_tickets.requester_name', 'like', $term)
                        ->orWhere('it_tickets.requester_phone', 'like', $term)
                        ->orWhere('it_tickets.description', 'like', $term)
                        ->orWhere('it_tickets.resolution_summary', 'like', $term)
                        ->orWhere('it_tickets.category', 'like', $term)
                        ->orWhere('it_ticket_categories.name', 'like', $term)
                        ->orWhere('assets.asset_number', 'like', $term)
                        ->orWhere('assets.description', 'like', $term)
                        ->orWhere('ticket_technicians.name', 'like', $term)
                        ->orWhere('ticket_departments.name', 'like', $term);
                })
                ->orderByDesc('it_tickets.id')
                ->limit(5)
                ->get();
        }

        if ($user->can('administracao.visualizar')) {
            $results['users'] = DB::table('users')
                ->select('id', 'name as title', 'email as subtitle')
                ->when(! $isAdmin && ! $user->can('administracao.aprovar'), fn ($query) => $query->where('department_id', $departmentId))
                ->where(fn ($query) => $query->where('name', 'like', $term)->orWhere('email', 'like', $term))
                ->limit(5)
                ->get();

            $results['departments'] = DB::table('departments')
                ->select('id', 'name as title', 'slug as subtitle')
                ->when(! $isAdmin && ! $user->can('administracao.aprovar'), fn ($query) => $query->where('id', $departmentId))
                ->where('name', 'like', $term)
                ->limit(5)
                ->get();
        }

        return response()->json($results);
    }
}
