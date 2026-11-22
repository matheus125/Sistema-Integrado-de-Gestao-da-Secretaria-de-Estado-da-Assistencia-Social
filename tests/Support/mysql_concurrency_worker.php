<?php

use App\Http\Controllers\Api\It\ItTicketController;
use App\Http\Controllers\Api\Materials\StockMovementController;
use App\Http\Controllers\Api\Transport\VehicleRequestController;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $scenario, $userId, $encodedPayload] = $argv + [null, null, null, null];
$payload = json_decode((string) base64_decode((string) $encodedPayload, true), true, flags: JSON_THROW_ON_ERROR);
$user = User::query()->findOrFail((int) $userId);
$request = Request::create('/concurrency-worker', 'POST', $payload, server: ['HTTP_ACCEPT' => 'application/json']);
$request->setUserResolver(static fn (): User => $user);

try {
    $response = match ($scenario) {
        'stock' => $app->make(StockMovementController::class)->store($request),
        'transport' => $app->make(VehicleRequestController::class)->store($request),
        'ticket' => $app->make(ItTicketController::class)->store($request),
        default => throw new InvalidArgumentException('Unknown concurrency scenario.'),
    };

    $data = $response->getData(true);
    fwrite(STDOUT, json_encode([
        'result' => 'created',
        'status' => $response->getStatusCode(),
        'id' => $data['id'] ?? null,
        'assigned_to' => $data['assigned_to'] ?? null,
    ], JSON_THROW_ON_ERROR));
} catch (ValidationException $exception) {
    fwrite(STDOUT, json_encode([
        'result' => 'validation',
        'fields' => array_keys($exception->errors()),
    ], JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(1);
}
