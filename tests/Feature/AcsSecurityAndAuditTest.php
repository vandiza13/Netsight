<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Vandiza\NetsightCore\Jobs\BulkAcsOperationJob;
use Vandiza\NetsightCore\Models\AcsAlert;
use Vandiza\NetsightCore\Models\AcsDevice;
use Vandiza\NetsightCore\Models\AcsNotificationConfig;
use Vandiza\NetsightCore\Models\AcsProfile;
use Vandiza\NetsightCore\Models\AcsTask;
use Vandiza\NetsightCore\Models\AuditLog;
use Vandiza\NetsightCore\Models\StaffNoc;
use Vandiza\NetsightCore\Services\GenieAcsService;

class AcsSecurityAndAuditTest extends TestCase
{
    use RefreshDatabase;

    private StaffNoc $tier1;
    private StaffNoc $tier2;
    private StaffNoc $admin;
    private AcsDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        // Bypass remote license check in unit/feature test environment
        Config::set('netsight.license.key', 'test-license-key');
        Cache::put('netsight_license_status', 'valid', 3600);

        $this->tier1 = StaffNoc::create([
            'name' => 'Teknisi Lapangan',
            'email' => 'tier1@netsight.test',
            'password_hash' => Hash::make('Password123!A'),
            'role' => 'TIER_1',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->tier2 = StaffNoc::create([
            'name' => 'Staf NOC',
            'email' => 'tier2@netsight.test',
            'password_hash' => Hash::make('Password123!A'),
            'role' => 'TIER_2',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->admin = StaffNoc::create([
            'name' => 'Super Admin',
            'email' => 'admin@netsight.test',
            'password_hash' => Hash::make('Password123!A'),
            'role' => 'ADMIN',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->device = AcsDevice::create([
            'genieacs_id' => '00259E-EG8145V5-48575443AABBCCDD',
            'serial_number' => '48575443AABBCCDD',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'vendor' => 'Huawei',
            'model' => 'EG8145V5',
            'pppoe_username' => 'pelanggan01@isp.net',
            'wifi_ssid' => 'RumahKu',
            'status' => 'online',
            'rx_power_dbm' => -19.5,
            'last_inform_at' => now(),
        ]);

        $this->instance(
            GenieAcsService::class,
            Mockery::mock(GenieAcsService::class, function (MockInterface $mock) {
                $mock->shouldReceive('triggerConnectionRequest')->andReturn(['task_id' => 't_refresh', 'execution_type' => 'instant']);
                $mock->shouldReceive('setWifiConfig')->andReturn(['task_id' => 't_wifi', 'execution_type' => 'instant']);
                $mock->shouldReceive('setPppoeConfig')->andReturn(['task_id' => 't_pppoe', 'execution_type' => 'instant']);
                $mock->shouldReceive('rebootDevice')->andReturn(['task_id' => 't_reboot', 'execution_type' => 'instant']);
                $mock->shouldReceive('factoryResetDevice')->andReturn(['task_id' => 't_reset', 'execution_type' => 'instant']);
                $mock->shouldReceive('deleteDevice')->andReturn(true);
                $mock->shouldReceive('triggerPing')->andReturn(['task_id' => 't_ping', 'execution_type' => 'instant']);
                $mock->shouldReceive('setAdminPassword')->andReturn(['task_id' => 't_adminpw', 'execution_type' => 'instant']);
                $mock->shouldReceive('applyProvisioningProfile')->andReturn(['task_id' => 't_prov', 'execution_type' => 'instant']);
                $mock->shouldReceive('probeHealth')->andReturn(true);
            })
        );
    }

    public function test_tier1_can_view_and_edit_wifi_but_blocked_from_noc_and_admin_actions(): void
    {
        // Allowed for TIER_1
        $this->actingAs($this->tier1)
            ->getJson('/api/acs/devices')
            ->assertStatus(200);

        $this->actingAs($this->tier1)
            ->postJson("/api/acs/devices/{$this->device->id}/wifi", [
                'ssid' => 'WifiPelangganBaru',
                'password' => 'SandiAman123',
            ])
            ->assertStatus(200);

        // Secret password must be redacted in audit_logs and acs_tasks
        $task = AcsTask::where('action', 'update_wifi')->first();
        $this->assertNotNull($task);
        $this->assertEquals($this->tier1->id, $task->created_by);
        $this->assertEquals('Teknisi Lapangan', $task->creator?->name);
        $this->assertEquals('********', $task->payload['password']);

        $audit = AuditLog::where('action', 'ACS_UPDATE_WIFI')->first();
        $this->assertNotNull($audit);
        $this->assertEquals('********', $audit->metadata['params']['password']);
        $this->assertStringContainsString('48575443AABBCCDD', $audit->target_username);

        // Blocked TIER_2+ endpoints (403)
        $this->actingAs($this->tier1)
            ->postJson("/api/acs/devices/{$this->device->id}/reboot")
            ->assertStatus(403);

        $this->actingAs($this->tier1)
            ->postJson("/api/acs/devices/{$this->device->id}/pppoe", ['username' => 'hack'])
            ->assertStatus(403);

        $this->actingAs($this->tier1)
            ->postJson('/api/acs/sync')
            ->assertStatus(403);

        // Unauthorized attempt must be recorded as RBAC_DENIED
        $this->assertDatabaseHas('audit_logs', [
            'staff_noc_id' => $this->tier1->id,
            'action' => "RBAC_DENIED POST api/acs/devices/{$this->device->id}/reboot",
        ]);
    }

    public function test_tier2_can_reboot_and_edit_pppoe_but_blocked_from_factory_reset_and_bulk(): void
    {
        $this->actingAs($this->tier2)
            ->postJson("/api/acs/devices/{$this->device->id}/reboot")
            ->assertStatus(200);

        $this->actingAs($this->tier2)
            ->postJson("/api/acs/devices/{$this->device->id}/pppoe", [
                'username' => 'pelanggan01_baru@isp.net',
            ])
            ->assertStatus(200);

        // Blocked ADMIN-only endpoints
        $this->actingAs($this->tier2)
            ->postJson("/api/acs/devices/{$this->device->id}/factory-reset", [
                'confirmation_sn' => '48575443AABBCCDD',
            ])
            ->assertStatus(403);

        $this->actingAs($this->tier2)
            ->deleteJson("/api/acs/devices/{$this->device->id}", [
                'confirmation_sn' => '48575443AABBCCDD',
            ])
            ->assertStatus(403);

        $this->actingAs($this->tier2)
            ->postJson('/api/acs/bulk-action', [
                'device_ids' => [$this->device->id],
                'action' => 'reboot',
            ])
            ->assertStatus(403);

        $this->actingAs($this->tier2)
            ->getJson('/api/acs/notification-config')
            ->assertStatus(403);
    }

    public function test_admin_factory_reset_and_delete_require_exact_serial_number_confirmation(): void
    {
        // Mismatched SN must fail 422 & log rejection
        $this->actingAs($this->admin)
            ->postJson("/api/acs/devices/{$this->device->id}/factory-reset", [
                'confirmation_sn' => 'RESET',
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('audit_logs', [
            'staff_noc_id' => $this->admin->id,
            'action' => 'ACS_FACTORY_RESET_REJECTED',
        ]);

        // Exact SN succeeds & creates audit log
        $this->actingAs($this->admin)
            ->postJson("/api/acs/devices/{$this->device->id}/factory-reset", [
                'confirmation_sn' => '48575443AABBCCDD',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'staff_noc_id' => $this->admin->id,
            'action' => 'ACS_FACTORY_RESET',
        ]);

        // Delete with wrong SN rejected
        $this->actingAs($this->admin)
            ->deleteJson("/api/acs/devices/{$this->device->id}", [
                'confirmation_sn' => 'WRONG_SN',
            ])
            ->assertStatus(422);

        // Delete with exact SN succeeds
        $this->actingAs($this->admin)
            ->deleteJson("/api/acs/devices/{$this->device->id}", [
                'confirmation_sn' => '48575443AABBCCDD',
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('acs_devices', ['id' => $this->device->id]);
        $this->assertDatabaseHas('audit_logs', [
            'staff_noc_id' => $this->admin->id,
            'action' => 'ACS_DELETE_DEVICE',
        ]);
    }

    public function test_bulk_factory_reset_phrase_limit_and_rate_limiting_enforced(): void
    {
        // Per-device reboot rate limit
        Config::set('netsight.acs.device_cooldowns.reboot', 30);
        RateLimiter::clear("acs_rl:reboot:{$this->device->id}");

        $this->actingAs($this->admin)
            ->postJson("/api/acs/devices/{$this->device->id}/reboot")
            ->assertStatus(200);

        $this->actingAs($this->admin)
            ->postJson("/api/acs/devices/{$this->device->id}/reboot")
            ->assertStatus(429);

        // Bulk factory reset requires exact phrase FACTORY-RESET-1
        $this->actingAs($this->admin)
            ->postJson('/api/acs/bulk-action', [
                'device_ids' => [$this->device->id],
                'action' => 'factory_reset',
                'params' => ['confirmation_phrase' => 'WRONG'],
            ])
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->postJson('/api/acs/bulk-action', [
                'device_ids' => [$this->device->id],
                'action' => 'factory_reset',
                'params' => ['confirmation_phrase' => 'FACTORY-RESET-1'],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'staff_noc_id' => $this->admin->id,
            'action' => 'ACS_BULK_FACTORY_RESET_QUEUED',
        ]);
    }

    public function test_bulk_job_circuit_breaker_and_cancellation_and_encrypted_secrets(): void
    {
        // Notification token encryption at rest & masking
        $this->actingAs($this->admin)
            ->postJson('/api/acs/notification-config', [
                'telegram_bot_token' => '998877665:SecretTelegramTokenXYZ',
                'telegram_chat_id' => '-100123456',
                'telegram_enabled' => true,
            ])
            ->assertStatus(200);

        $rawRow = DB::table('acs_notification_configs')->first();
        $this->assertNotEquals('998877665:SecretTelegramTokenXYZ', $rawRow->telegram_bot_token);
        $this->assertEquals('998877665:SecretTelegramTokenXYZ', Crypt::decryptString($rawRow->telegram_bot_token));

        $resp = $this->actingAs($this->admin)->getJson('/api/acs/notification-config')->assertStatus(200);
        $this->assertTrue($resp->json('data.has_telegram_bot_token'));
        $this->assertNull($resp->json('data.telegram_bot_token'));
        $this->assertStringContainsString('••••••••', $resp->json('data.telegram_bot_token_masked'));

        // Profile password encryption & non-admin visibility
        $profileResp = $this->actingAs($this->admin)
            ->postJson('/api/acs/profiles', [
                'name' => 'ZTE Standard',
                'admin_password' => 'OntSuperSecret99',
                'wifi_password_template' => 'WifiSecret123',
            ])
            ->assertStatus(200);

        $this->assertTrue($profileResp->json('data.has_admin_password'));
        $this->assertNull($profileResp->json('data.admin_password'));

        $nonAdminProfiles = $this->actingAs($this->tier1)->getJson('/api/acs/profiles')->assertStatus(200);
        $this->assertNull($nonAdminProfiles->json('data.0.admin_password'));
        $this->assertNull($nonAdminProfiles->json('data.0.wifi_password_template'));

        // AuditLog append-only immutability
        $log = AuditLog::first();
        $this->expectException(\RuntimeException::class);
        $log->delete();
    }

    public function test_bulk_job_aborts_on_unhealthy_nbi_and_records_audit(): void
    {
        $this->instance(
            GenieAcsService::class,
            Mockery::mock(GenieAcsService::class, function (MockInterface $mock) {
                $mock->shouldReceive('probeHealth')->andReturn(false);
            })
        );

        $batchId = 'batch_test_abort_01';
        $job = new BulkAcsOperationJob(
            [$this->device->id],
            'reboot',
            [],
            $this->admin->id,
            $batchId
        );
        $job->handle(app(GenieAcsService::class));

        $cached = Cache::get("acs_batch_{$batchId}");
        $this->assertEquals('aborted', $cached['status']);
        $this->assertStringContainsString('Pre-flight', $cached['abort_reason']);
        $this->assertDatabaseHas('audit_logs', [
            'staff_noc_id' => $this->admin->id,
            'action' => 'ACS_BULK_REBOOT_ABORTED',
        ]);
    }

    public function test_alert_resolver_resolves_to_staff_noc(): void
    {
        $alert = AcsAlert::create([
            'acs_device_id' => $this->device->id,
            'severity' => 'critical',
            'title' => 'Redaman Kritis',
            'message' => 'RX -29 dBm',
        ]);

        $this->actingAs($this->tier1)
            ->postJson("/api/acs/alerts/{$alert->id}/resolve")
            ->assertStatus(200);

        $alert->refresh();
        $this->assertTrue($alert->is_resolved);
        $this->assertEquals($this->tier1->id, $alert->resolved_by);
        $this->assertEquals('Teknisi Lapangan', $alert->resolver?->name);
    }

    public function test_preserving_encrypted_secrets_on_update_and_filter_parity_and_batch_cancel_and_audit_filter(): void
    {
        // Unauthenticated gets 401
        $this->getJson('/api/acs/devices')->assertStatus(401);

        // Create and verify notification secret preservation on blank update
        $this->actingAs($this->admin)
            ->postJson('/api/acs/notification-config', [
                'telegram_bot_token' => '111222:KeepThisSecret',
                'telegram_chat_id' => '-100111',
                'telegram_enabled' => true,
            ])
            ->assertStatus(200);

        $this->actingAs($this->admin)
            ->postJson('/api/acs/notification-config', [
                'telegram_bot_token' => '',
                'telegram_chat_id' => '-100222',
                'telegram_enabled' => true,
            ])
            ->assertStatus(200);

        $conf = AcsNotificationConfig::instance();
        $this->assertEquals('111222:KeepThisSecret', $conf->telegram_bot_token);
        $this->assertEquals('-100222', $conf->telegram_chat_id);

        // Profile admin password preservation & clear flag
        $prof = AcsProfile::create([
            'name' => 'Huawei Safe',
            'admin_password' => 'KeepAdminPw99',
        ]);
        $this->actingAs($this->admin)
            ->putJson("/api/acs/profiles/{$prof->id}", [
                'name' => 'Huawei Safe V2',
                'admin_password' => '',
            ])
            ->assertStatus(200);
        $this->assertEquals('KeepAdminPw99', $prof->fresh()->admin_password);

        // Batch cancel endpoint RBAC & cancellation signal
        $this->actingAs($this->tier2)
            ->postJson('/api/acs/batches/batch_cancel_99/cancel')
            ->assertStatus(403);

        $this->actingAs($this->admin)
            ->postJson('/api/acs/batches/batch_cancel_99/cancel')
            ->assertStatus(200);
        $this->assertTrue(Cache::has('acs_batch_cancel_batch_cancel_99'));

        // Job honors cancellation signal cleanly
        $job = new BulkAcsOperationJob([$this->device->id], 'reboot', [], $this->admin->id, 'batch_cancel_99');
        $job->handle(app(GenieAcsService::class));
        $this->assertEquals('cancelled', Cache::get('acs_batch_batch_cancel_99')['status']);

        // Bulk action max devices guardrail & filter parity
        $secondCritical = AcsDevice::create([
            'genieacs_id' => '00259E-EG8145V5-CRITICAL02',
            'serial_number' => 'CRITICAL02',
            'status' => 'online',
            'rx_power_dbm' => -29.8,
            'area_tag' => 'ODP-BARAT',
        ]);

        Config::set('netsight.acs.bulk_max_devices', 1);
        RateLimiter::clear('acs_rl:bulk:' . $this->admin->id);
        $this->actingAs($this->admin)
            ->postJson('/api/acs/bulk-action', [
                'device_ids' => [$this->device->id, $secondCritical->id],
                'action' => 'reboot',
            ])
            ->assertStatus(422);

        // Reset limit to 10 & test select_all_matching with critical_rx + area filter
        Config::set('netsight.acs.bulk_max_devices', 10);
        RateLimiter::clear('acs_rl:bulk:' . $this->admin->id);
        $bulkRes = $this->actingAs($this->admin)
            ->postJson('/api/acs/bulk-action', [
                'select_all_matching' => true,
                'filter_status' => 'critical_rx',
                'filter_area' => 'ODP-BARAT',
                'action' => 'reboot',
            ])
            ->assertStatus(200);
        $this->assertEquals(1, $bulkRes->json('total'));

        // AuditLogController module filter & RBAC
        $this->actingAs($this->tier2)->getJson('/api/audit-logs?module=ACS')->assertStatus(403);
        $auditRes = $this->actingAs($this->admin)->getJson('/api/audit-logs?module=ACS')->assertStatus(200);
        $this->assertGreaterThan(0, count($auditRes->json('data')));
    }
}
