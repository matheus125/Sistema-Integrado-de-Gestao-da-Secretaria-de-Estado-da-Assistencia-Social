<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $modules = collect(config('siges.modules'))
            ->filter(fn (array $module, string $key) => $request->user()->can("{$key}.visualizar"))
            ->all();

        return response()->json([
            'modules' => $modules,
            'actions' => config('siges.actions'),
        ]);
    }
}
