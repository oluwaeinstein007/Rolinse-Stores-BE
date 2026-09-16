<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_history_requires_a_user_or_an_email_not_a_bare_request(): void
    {
        // user_email is NOT NULL at the schema level, so the previous
        // where('user_email', null) fallback could never actually leak
        // another customer's orders — it just silently returned an empty
        // list for a malformed/anonymous request. Still worth a clear 422
        // over a misleading "success, no orders" response.
        $this->getJson('/api/v1/user/orders/history')->assertStatus(422);
    }

    public function test_order_history_works_with_an_explicit_email(): void
    {
        Order::create([
            'user_email' => 'jane@example.com',
            'order_number' => 'ORD-JANE1',
            'status' => 'pending',
            'grand_total' => 100,
            'grand_total_ngn' => 100,
            'item_count' => 1,
        ]);

        $response = $this->getJson('/api/v1/user/orders/history?email=jane@example.com');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_update_order_status_rejects_an_invalid_status(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::create([
            'user_email' => 'jane@example.com',
            'order_number' => 'ORD-JANE2',
            'status' => 'pending',
            'grand_total' => 100,
            'grand_total_ngn' => 100,
            'item_count' => 1,
        ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/orders/update-status/{$order->id}", ['status' => 'not-a-real-status'])
            ->assertStatus(422);
    }

    public function test_update_order_status_accepts_a_valid_status(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::create([
            'user_email' => 'jane@example.com',
            'order_number' => 'ORD-JANE3',
            'status' => 'pending',
            'grand_total' => 100,
            'grand_total_ngn' => 100,
            'item_count' => 1,
        ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/orders/update-status/{$order->id}", ['status' => 'completed'])
            ->assertOk();

        $this->assertEquals('completed', $order->fresh()->status);
    }
}
