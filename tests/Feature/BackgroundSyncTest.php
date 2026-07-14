<?php

namespace Tests\Feature;

use App\Jobs\SyncRouterUsersJob;
use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackgroundSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_staggered_cron_dispatches_jobs_only_for_matching_offset()
    {
        Queue::fake();

        $currentMinute = now()->minute;

        // Router that should be synced now
        $routerNow = Router::factory()->create([
            'sync_offset_minutes' => $currentMinute,
        ]);

        // Router that should NOT be synced now
        $routerLater = Router::factory()->create([
            'sync_offset_minutes' => ($currentMinute + 1) % 60,
        ]);

        // Run the console command directly
        $this->artisan('schedule:run');

        Queue::assertPushed(SyncRouterUsersJob::class, function ($job) use ($routerNow) {
            return $job->router->id === $routerNow->id;
        });

        Queue::assertNotPushed(SyncRouterUsersJob::class, function ($job) use ($routerLater) {
            return $job->router->id === $routerLater->id;
        });
    }

    public function test_circuit_breaker_prevents_sync_if_degraded()
    {
        // This tests the logic in SyncService where it checks isDegraded()
        $router = Router::factory()->create([
            'status' => 'DEGRADED',
            'consecutive_sync_failures' => 3,
        ]);

        $service = new \App\Services\SyncService(app(\App\Services\MikrotikApiService::class));
        $result = $service->syncRouter($router);

        $this->assertEquals('SKIPPED', $result['status']);
        $this->assertEquals('Router is DEGRADED (circuit breaker active)', $result['reason']);
    }
}
