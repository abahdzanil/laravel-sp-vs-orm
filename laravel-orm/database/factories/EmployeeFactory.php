<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'          => fake()->name(),
            'email'         => fake()->unique()->safeEmail(),
            'position'      => fake()->randomElement(['Staff', 'Supervisor', 'Manager']),
            'department'    => fake()->randomElement(['IT', 'Finance', 'HR', 'Operasional']),
            'salary'        => fake()->numberBetween(4000000, 25000000),
            'hire_date'     => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
        ];
    }
}
