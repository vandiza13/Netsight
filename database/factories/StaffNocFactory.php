<?php

namespace Database\Factories;

use App\Models\StaffNoc;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class StaffNocFactory extends Factory
{
    protected $model = StaffNoc::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password_hash' => Hash::make('password'),
            'role' => 'TIER_1',
            'is_active' => true,
        ];
    }
}
