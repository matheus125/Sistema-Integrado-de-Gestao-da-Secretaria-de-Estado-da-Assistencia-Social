<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class AdminOptionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'departments' => Department::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'roles' => Role::query()->orderBy('name')->pluck('name')->values(),
        ]);
    }
}
