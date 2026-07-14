<?php

namespace Tests\Feature;

use App\Models\Router;
use App\Models\StaffNoc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ApiFuzzTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private StaffNoc $staff;
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->staff = StaffNoc::factory()->create([
            'role' => 'TIER_2'
        ]);

        $this->router = Router::factory()->create();

        // Mock the guardrail service
        $this->instance(
            \App\Services\TorchGuardrailService::class,
            \Mockery::mock(\App\Services\TorchGuardrailService::class, function (\Mockery\MockInterface $mock) {
                $mock->shouldReceive('preFlight')->andReturn(['allowed' => true, 'cpu_load' => 20, 'message' => 'CPU OK.']);
                $mock->shouldReceive('checkConcurrency')->andReturn(true);
                $mock->shouldReceive('generateTag')->andReturn('TEST-TAG-123');
                $mock->shouldReceive('acquireHeartbeatLock')->andReturn(true);
            })
        );
        
        // Mock Mikrotik API
        $this->instance(
            \App\Services\MikrotikApiService::class,
            \Mockery::mock(\App\Services\MikrotikApiService::class, function (\Mockery\MockInterface $mock) {
                $mock->shouldReceive('getActivePppoeInterface')->andReturnNull(); // Simulate user not found
            })
        );
    }

    /**
     * Fuzzing API Endpoint Inspect untuk mendeteksi kelemahan Injeksi / Type Juggling
     */
    public function test_torch_inspect_endpoint_resists_fuzzing()
    {
        // 1. Invalid Router ID Type (String instead of Int)
        $response = $this->actingAs($this->staff)->postJson('/api/torch/inspect', [
            'router_id' => 'INJECT_STRING_ID',
            'username' => 'test-user',
        ]);
        $response->assertStatus(422);

        // 2. Extremely Long Username (Buffer Overflow Attempt)
        $response = $this->actingAs($this->staff)->postJson('/api/torch/inspect', [
            'router_id' => $this->router->id,
            'username' => str_repeat('A', 5000), 
        ]);
        $response->assertStatus(422); // Harus gagal validasi max:100

        // 3. SQL Injection Attempt on Username
        $response = $this->actingAs($this->staff)->postJson('/api/torch/inspect', [
            'router_id' => $this->router->id,
            'username' => "admin' OR 1=1 --", 
        ]);
        // Boleh 404 karena user PPPoE dengan nama tersebut tidak ada, asalkan tidak 500
        $this->assertNotEquals(500, $response->status());

        // 4. Missing Parameters
        $response = $this->actingAs($this->staff)->postJson('/api/torch/inspect', []);
        $response->assertStatus(422);
    }
}
