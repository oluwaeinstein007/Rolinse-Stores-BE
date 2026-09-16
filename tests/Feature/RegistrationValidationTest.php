<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * createAccount's validator was more lenient ("nullable") than the users
 * table (first_name/last_name/phone_number/country are NOT NULL, and
 * password had no rule at all) — omitting any of them crashed with a raw
 * 500 QueryException at the INSERT instead of a clean 422.
 */
class RegistrationValidationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'new.customer@example.com',
            'password' => 'SecurePass123!',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'phone_number' => '+2348000000001',
            'country' => 'Nigeria',
        ], $overrides);
    }

    public function test_registration_succeeds_with_all_required_fields(): void
    {
        $response = $this->postJson('/api/v1/user/register', $this->validPayload());

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'new.customer@example.com']);
    }

    public function test_registration_rejects_a_missing_password_instead_of_crashing(): void
    {
        $payload = $this->validPayload();
        unset($payload['password']);

        $response = $this->postJson('/api/v1/user/register', $payload);

        $response->assertStatus(422)->assertJsonPath('errors.password.0', fn ($m) => is_string($m));
    }

    public function test_registration_rejects_a_missing_country_instead_of_crashing(): void
    {
        $payload = $this->validPayload();
        unset($payload['country']);

        $response = $this->postJson('/api/v1/user/register', $payload);

        $response->assertStatus(422);
    }

    public function test_registration_rejects_a_missing_phone_number_instead_of_crashing(): void
    {
        $payload = $this->validPayload();
        unset($payload['phone_number']);

        $response = $this->postJson('/api/v1/user/register', $payload);

        $response->assertStatus(422);
    }
}
