<?php

namespace App\Http\Controllers\Api\Audit;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'module' => ['nullable', 'string', 'max:80'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $logs = Activity::query()
            ->with('causer:id,name,email')
            ->when($data['module'] ?? null, fn ($query, $module) => $query->where('log_name', $module))
            ->when($data['user_id'] ?? null, fn ($query, $userId) => $query->where('causer_id', $userId)->where('causer_type', User::class))
            ->when($data['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($data['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%")
                    ->orWhere('log_name', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        $logs->getCollection()->transform(function (Activity $activity) {
            return [
                'id' => $activity->id,
                'module' => $activity->log_name,
                'description' => $activity->description,
                'event' => $activity->event,
                'causer' => $activity->causer ? [
                    'id' => $activity->causer->id,
                    'name' => $activity->causer->name,
                    'email' => $activity->causer->email,
                ] : null,
                'subject_type' => class_basename((string) $activity->subject_type),
                'subject_id' => $activity->subject_id,
                'properties' => $activity->properties,
                'created_at' => $activity->created_at?->toISOString(),
            ];
        });

        return response()->json($logs);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'modules' => DB::table('activity_log')
                ->whereNotNull('log_name')
                ->distinct()
                ->orderBy('log_name')
                ->pluck('log_name')
                ->values(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }
}
