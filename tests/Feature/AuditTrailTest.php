<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Router;
use App\Models\StaffNoc;
use App\Services\TorchGuardrailService;
use App\Services\MikrotikApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private StaffNoc $staff;
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->staff = StaffNoc::factory()->create([
            'role' => 'TIER_2'
        ]);

        $this->router = Router::factory()->create([
            'name' => 'Core Router',
            'host' => '10.0.0.1'
        ]);
        
        // Mock the guardrail service to always allow
        $this->instance(
            TorchGuardrailService::class,
            Mockery::mock(TorchGuardrailService::class, function (MockInterface $mock) {
                $mock->shouldReceive('preFlight')->andReturn(['allowed' => true, 'cpu_load' => 20, 'message' => 'CPU OK.']);
                $mock->shouldReceive('checkConcurrency')->andReturn(true);
                $mock->shouldReceive('generateTag')->andReturn('TEST-TAG-123');
                $mock->shouldReceive('acquireHeartbeatLock')->andReturn(true);
                $mock->shouldReceive('releaseHeartbeat')->andReturn(true);
            })
        );
        
        // Mock Mikrotik API
        $this->instance(
            MikrotikApiService::class,
            Mockery::mock(MikrotikApiService::class, function (MockInterface $mock) {
                $mock->shouldReceive('getActivePppoeInterface')->andReturn('<pppoe-test-user>');
                $mock->shouldReceive('cancelTorch')->andReturn(true);
            })
        );
    }

    public function test_torch_inspect_creates_audit_log()
    {
        $response = $this->actingAs($this->staff)->postJson('/api/torch/inspect', [
            'router_id' => $this->router->id,
            'username' => 'test-user',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'staff_noc_id' => $this->staff->id,
            'action' => 'START_TORCH',
            'target_username' => 'test-user',
            'router_id' => $this->router->id,
        ]);
        
        $log = AuditLog::where('action', 'START_TORCH')->first();
        $this->assertEquals('TEST-TAG-123', $log->metadata['session_tag']);
        $this->assertEquals('<pppoe-test-user>', $log->metadata['interface']);
        $this->assertEquals(20, $log->metadata['cpu_load']);
    }

    public function test_torch_cancel_creates_audit_log()
    {
        // Setup initial inspect to create session
        $this->actingAs($this->staff)->postJson('/api/torch/inspect', [
            'router_id' => $this->router->id,
            'username' => 'test-user',
        ]);

        // Hit cancel
        $response = $this->actingAs($this->staff)->postJson('/api/torch/TEST-TAG-123/cancel');
        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'staff_noc_id' => $this->staff->id,
            'action' => 'CANCEL_TORCH',
            'target_username' => 'test-user',
            'router_id' => $this->router->id,
        ]);
        
        $log = AuditLog::where('action', 'CANCEL_TORCH')->first();
        $this->assertEquals('TEST-TAG-123', $log->metadata['session_tag']);
    }
}
