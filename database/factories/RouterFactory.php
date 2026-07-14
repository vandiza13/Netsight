<?php

namespace Database\Factories;

use App\Models\Router;
use Illuminate\Database\Eloquent\Factories\Factory;

class RouterFactory extends Factory
{
    protected $model = Router::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company . ' Router',
            'host' => $this->faker->ipv4,
            'api_port' => 8729,
            'credential_encrypted' => 'admin:password123', // Will be encrypted by model mutator
            'routeros_version' => '7.12.1',
            'sync_offset_minutes' => $this->faker->numberBetween(0, 59),
            'status' => 'HEALTHY',
            'last_synced_at' => null,
            'consecutive_sync_failures' => 0,
        ];
    }
}
