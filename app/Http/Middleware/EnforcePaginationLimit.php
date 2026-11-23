<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class EnforcePaginationLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->exists('per_page')) {
            return $next($request);
        }

        $maximum = max(1, (int) config('pagination.max_per_page', 100));
        $validator = Validator::make(
            ['per_page' => $request->input('per_page')],
            ['per_page' => ['required', 'integer', 'min:1', "max:{$maximum}"]],
            [
                'per_page.required' => 'A quantidade por página deve ser informada.',
                'per_page.integer' => 'A quantidade por página deve ser um número inteiro.',
                'per_page.min' => 'A quantidade por página deve ser pelo menos 1.',
                'per_page.max' => "A quantidade por página não pode ser maior que {$maximum}.",
            ],
        );

        if ($validator->fails()) {
            return new JsonResponse([
                'message' => 'A quantidade de registros por página é inválida.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $request->merge(['per_page' => (int) $request->input('per_page')]);

        return $next($request);
    }
}
