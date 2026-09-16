<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks in the fixes for the delivery routes, which previously had no
 * middleware at all (create/search/track/update shipments, read customer
 * PII) and a webhook handler that crashed on an unrecognised delivery id.
 */
class DeliverySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_management_routes_require_an_authenticated_admin(): void
    {
        $this->postJson('/api/v1/delivery/create-order', [])
            ->assertUnauthorized();
    }

    public function test_delivery_management_routes_reject_a_non_admin_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/delivery/create-order', [])
            ->assertForbidden();
    }

    public function test_delivery_webhook_rejects_a_request_without_the_shared_secret(): void
    {
        config(['services.webhook_shared_secret' => 'test-secret']);

        $this->postJson('/api/v1/delivery/webhook', ['orderNumber' => 'x', 'status' => 'Delivered'])
            ->assertUnauthorized();
    }

    public function test_delivery_webhook_accepts_a_request_with_the_correct_shared_secret(): void
    {
        config(['services.webhook_shared_secret' => 'test-secret']);

        $order = Order::create([
            'user_email' => 'customer@example.com',
            'order_number' => 'ORD-TEST1',
            'status' => 'pending',
            'grand_total' => 100,
            'grand_total_ngn' => 100,
            'item_count' => 1,
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'recipientAddress' => '1 Test Street',
            'recipientState' => 'Lagos',
            'recipientName' => 'Test Recipient',
            'recipientPhone' => '+2348000000000',
            'weight' => 1,
            'pickup_state' => 'Lagos',
            'email' => 'customer@example.com',
            'uniqueID' => 'ORD-TEST1',
            'CustToken' => 'ORD-TEST1',
            'BatchID' => 'BATCHTEST1',
            'valueOfItem' => 100,
            'delivery_order_id' => 'FEZ123',
            'delivery_status' => 'Pending Pick-Up',
        ]);

        $response = $this->postJson('/api/v1/delivery/webhook', [
            'orderNumber' => 'FEZ123',
            'status' => 'Delivered',
        ], ['X-Webhook-Secret' => 'test-secret']);

        $response->assertOk();
        $this->assertEquals('Delivered', Delivery::first()->delivery_status);
    }

    public function test_delivery_webhook_for_an_unknown_delivery_id_returns_404_not_a_crash(): void
    {
        config(['services.webhook_shared_secret' => 'test-secret']);

        $response = $this->postJson('/api/v1/delivery/webhook', [
            'orderNumber' => 'does-not-exist',
            'status' => 'Delivered',
        ], ['X-Webhook-Secret' => 'test-secret']);

        $response->assertStatus(404);
    }
}
