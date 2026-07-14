<?php

namespace Tests\Feature;

use App\Models\Router;
use App\Models\StaffNoc;
use App\Models\TorchSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class TorchEngineTest extends TestCase
{
    use RefreshDatabase;

    private StaffNoc $tier2User;
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tier2User = StaffNoc::factory()->create(['role' => 'TIER_2']);
        $this->router = Router::factory()->create([
            'status' => 'HEALTHY'
        ]);

        // Mock Redis to bypass missing phpredis extension in local test environment
        Redis::shouldReceive('flushall')->andReturn(true);
        Redis::shouldReceive('set')->andReturn(true);
        Redis::shouldReceive('exists')->andReturn(true);
        Redis::shouldReceive('expire')->andReturn(true);
        Redis::shouldReceive('del')->andReturn(1);
    }

    public function test_torch_inspect_creates_session_and_acquires_lock()
    {
        // Mock Mikrotik API
        $this->mock(\App\Services\MikrotikApiService::class, function ($mock) {
            $mock->shouldReceive('getSystemResource')->once()->andReturn([
                'cpu_load' => 20,
                'free_memory' => 1000000,
                'total_memory' => 2000000,
            ]);
            $mock->shouldReceive('getActivePppoeInterface')->once()->andReturn('<pppoe-testuser>');
        });

        $response = $this->actingAs($this->tier2User, 'sanctum')
            ->postJson('/api/torch/inspect', [
                'router_id' => $this->router->id,
                'username' => 'testuser',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['session_tag', 'interface']);

        $tag = $response->json('session_tag');
        
        // Assert DB
        $this->assertDatabaseHas('torch_sessions', [
            'router_id' => $this->router->id,
            'username' => 'testuser',
            'tag' => $tag,
            'status' => 'RUNNING'
        ]);

        // Assert Redis lock exists
        $this->assertTrue((bool) Redis::exists("torch:heartbeat:{$tag}"));
    }

    public function test_torch_inspect_fails_if_cpu_high()
    {
        $this->mock(\App\Services\MikrotikApiService::class, function ($mock) {
            $mock->shouldReceive('getSystemResource')->once()->andReturn([
                'cpu_load' => 95, // Above 80 threshold
            ]);
        });

        $response = $this->actingAs($this->tier2User, 'sanctum')
            ->postJson('/api/torch/inspect', [
                'router_id' => $this->router->id,
                'username' => 'testuser',
            ]);

        $response->assertStatus(503);
        $response->assertJsonFragment([
            'cpu_load' => 95,
        ]);
        
        $this->assertDatabaseEmpty('torch_sessions');
    }

    public function test_torch_inspect_enforces_concurrency_limit()
    {
        // Force 2 running sessions (limit is 2 by default config)
        TorchSession::factory()->count(2)->create([
            'router_id' => $this->router->id,
            'status' => 'RUNNING'
        ]);

        $this->mock(\App\Services\MikrotikApiService::class, function ($mock) {
            $mock->shouldReceive('getSystemResource')->once()->andReturn([
                'cpu_load' => 20,
            ]);
        });

        $response = $this->actingAs($this->tier2User, 'sanctum')
            ->postJson('/api/torch/inspect', [
                'router_id' => $this->router->id,
                'username' => 'testuser',
            ]);

        $response->assertStatus(429);
        $this->assertDatabaseCount('torch_sessions', 2);
    }
}
