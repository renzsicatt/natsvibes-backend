<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = ['database' => $this->check(fn () => DB::select('select 1')), 'cache' => $this->check(function (): void {
            Cache::put('health-check', 'ok', 10);
            if (Cache::get('health-check') !== 'ok') {
                throw new \RuntimeException('Cache read failed.');
            }
        })];
        $healthy = ! in_array('down', $checks, true);

        return response()->json(['data' => ['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks, 'timestamp' => now()->toIso8601String()]], $healthy ? 200 : 503);
    }

    private function check(callable $check): string
    {
        try {
            $check();

            return 'up';
        } catch (Throwable) {
            return 'down';
        }
    }
}
