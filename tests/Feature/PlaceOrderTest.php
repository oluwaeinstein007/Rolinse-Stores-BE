<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * placeOrder previously crashed with a raw 500 on almost any realistic
 * request: $user (rebuilt as a plain array partway through the method) was
 * still accessed with -> property syntax further down, is_benin/is_nigeria
 * defaulted to the international-export path when omitted, and
 * delivery_details.weight/pickup_state had no validation despite being
 * NOT NULL columns on `deliveries`. Cache exchange rates directly so these
 * tests don't depend on the live forex API being reachable.
 */
class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ExchangeRate::create(['currencyCode' => 'USD', 'rate' => 1]);
        ExchangeRate::create(['currencyCode' => 'NGN', 'rate' => 1500]);
    }

    private function authHeaders(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function makeProduct(): Product
    {
        $category = Category::factory()->create();

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'description' => 'A product for testing.',
            'material' => 'Cotton',
            'price' => 25,
        ]);
    }

    public function test_placing_an_order_with_all_required_fields_succeeds(): void
    {
        $product = $this->makeProduct();

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v1/user/orders', [
            'products' => [['product_id' => $product->id, 'quantity' => 1]],
            'returnCurrency' => 'USD',
            'delivery_details' => [
                'recipientAddress' => '1 Test Street',
                'recipientState' => 'Lagos',
                'recipientPhone' => '+2348000000000',
                'weight' => 1.5,
                'pickup_state' => 'Abuja',
                'is_nigeria' => true,
                'is_benin' => false,
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_placing_an_order_without_weight_returns_a_clean_422_not_a_500(): void
    {
        $product = $this->makeProduct();

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v1/user/orders', [
            'products' => [['product_id' => $product->id, 'quantity' => 1]],
            'returnCurrency' => 'USD',
            'delivery_details' => [
                'recipientAddress' => '1 Test Street',
                'recipientState' => 'Lagos',
                'recipientPhone' => '+2348000000000',
                'pickup_state' => 'Abuja',
                'is_nigeria' => true,
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('delivery_details.weight');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_placing_an_order_without_recipient_address_returns_a_clean_422(): void
    {
        $product = $this->makeProduct();

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/v1/user/orders', [
            'products' => [['product_id' => $product->id, 'quantity' => 1]],
            'returnCurrency' => 'USD',
            'delivery_details' => [
                'recipientState' => 'Lagos',
                'recipientPhone' => '+2348000000000',
                'weight' => 1,
                'pickup_state' => 'Abuja',
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('delivery_details.recipientAddress');
    }
}
