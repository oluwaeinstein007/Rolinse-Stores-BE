<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * Previously only set name/email/password — the real users table (see
     * 0001_01_01_000000_create_users_table.php) requires first_name,
     * last_name, country, and phone_number with no defaults, so
     * User::factory()->create() failed outright with a DB error. That's
     * very likely why this project has no real test coverage: writing any
     * test needing a user hit this immediately.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'country' => fake()->country(),
            'phone_number' => fake()->numerify('+234##########'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'user_role_id' => 2, // Regular user — see App\Http\Middleware\Admin (role 1 = admin)
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** Admin per App\Http\Middleware\Admin's check (user_role_id === 1). */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_role_id' => 1,
        ]);
    }
}
