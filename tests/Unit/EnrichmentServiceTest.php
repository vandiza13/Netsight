<?php

namespace Tests\Unit;

use App\Services\EnrichmentService;
use Tests\TestCase;

class EnrichmentServiceTest extends TestCase
{
    private EnrichmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EnrichmentService();
    }

    public function test_classify_port_identifies_known_ports()
    {
        $result = $this->service->classifyPort(443);
        $this->assertEquals('Web', $result['category']);
        $this->assertEquals('HTTPS', $result['service']);

        $result = $this->service->classifyPort(53);
        $this->assertEquals('DNS', $result['category']);
        
        $result = $this->service->classifyPort(27015); // Gaming
        $this->assertEquals('Gaming', $result['category']);
        
        $result = $this->service->classifyPort(6881); // Torrent
        $this->assertEquals('P2P', $result['category']);
    }

    public function test_classify_port_defaults_to_unknown_for_unmapped_ports()
    {
        $result = $this->service->classifyPort(9999);
        $this->assertEquals('Other', $result['category']);
        $this->assertEquals('Unknown', $result['service']);
    }

    public function test_geoip_lookup_identifies_private_ips()
    {
        $result = $this->service->geoIpLookup('192.168.1.5');
        $this->assertEquals('Local Network', $result['country']);
        $this->assertEquals('Internal', $result['isp']);

        $result = $this->service->geoIpLookup('10.0.0.5');
        $this->assertEquals('Local Network', $result['country']);
    }

    public function test_geoip_lookup_handles_missing_db_gracefully()
    {
        // This will attempt to look up a public IP without the real MMDB file (which we didn't download)
        $result = $this->service->geoIpLookup('8.8.8.8');
        
        // Should gracefully fallback to Unknown since file is missing or reader throws exception
        $this->assertEquals('Unknown', $result['country']);
        $this->assertEquals('-', $result['city']);
    }
}
