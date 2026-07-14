<?php

namespace Database\Factories;

use App\Models\Router;
use App\Models\StaffNoc;
use App\Models\TorchSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class TorchSessionFactory extends Factory
{
    protected $model = TorchSession::class;

    public function definition(): array
    {
        return [
            'router_id' => Router::factory(),
            'username' => $this->faker->userName,
            'session_id_snapshot' => $this->faker->uuid,
            'dynamic_interface' => '<pppoe-test>',
            'initiated_by' => StaffNoc::factory(),
            'tag' => 'torch-' . bin2hex(random_bytes(8)),
            'status' => 'RUNNING',
            'auto_cleanup' => false,
            'started_at' => now(),
        ];
    }
}
