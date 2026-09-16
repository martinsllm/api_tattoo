<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = $this->checkDatabase();
        $queue = $this->checkQueue();
        $storage = $this->checkStorage();

        $status = $database && $queue && $storage ? 200 : 503;

        return response()->json([
            'status' => $status === 200 ? 'ok' : 'fail',
            'database' => $database ? 'ok' : 'fail',
            'queue' => $queue ? 'ok' : 'fail',
            'storage' => $storage ? 'ok' : 'fail',
            'timestamp' => now()->toIso8601String(),
        ], $status);

    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function checkQueue(): bool
    {
        try {
            DB::table('jobs')->limit(1)->exists();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        try {
            $disk = Storage::disk(config('filesystems.artist_images_disk'));
            $upload = $disk->put('.health-check', 'temp');

            if (! $upload) {
                return false;
            }

            $disk->delete('.health-check');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
