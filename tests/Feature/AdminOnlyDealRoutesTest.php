<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SpecialDeals;
use App\Models\User;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * add-product, clear-product and handleImages used to be reachable without
 * login (Optional middleware only). They now sit behind auth:sanctum + Admin
 * like /admin/products, at the same URLs so the frontend doesn't change.
 * Deal-type create/delete are covered in CloudinaryUploadsTest.
 */
class AdminOnlyDealRoutesTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Nothing here may reach the real Cloudinary API.
        $uploadApi = Mockery::mock(UploadApi::class);
        $uploadApi->shouldNotReceive('upload');
        $sdk = Mockery::mock(Cloudinary::class);
        $sdk->shouldReceive('uploadApi')->andReturn($uploadApi);
        $this->instance(Cloudinary::class, $sdk);

        Storage::fake(config('filesystems.default'));

        SpecialDeals::create(['deal_type' => 'Black Friday', 'slug' => 'black_friday']);

        $this->product = Product::create([
            'category_id' => Category::factory()->create()->id,
            'name' => 'Linen Shirt',
            'description' => 'A shirt',
            'material' => 'Linen',
            'price' => 25,
        ]);
    }

    private function addProduct()
    {
        return $this->postJson('/api/v1/user/deals/add-product', [
            'deal_slug' => 'black_friday',
            'products' => [['product_id' => $this->product->id, 'discount' => 100]],
        ]);
    }

    private function clearProduct()
    {
        return $this->deleteJson('/api/v1/user/deals/clear-product', ['product_ids' => [$this->product->id]]);
    }

    private function handleImages()
    {
        return $this->getJson('/api/v1/user/products/handleImages');
    }

    private function assertProductHasNoDeal(): void
    {
        $product = $this->product->fresh();
        $this->assertNull($product->special_deal_slug);
        $this->assertEquals(0, $product->discount);
    }

    public function test_guests_are_refused(): void
    {
        $this->addProduct()->assertUnauthorized();
        $this->clearProduct()->assertUnauthorized();
        $this->handleImages()->assertUnauthorized();

        $this->assertProductHasNoDeal();
    }

    public function test_non_admins_are_refused(): void
    {
        $this->actingAs(User::factory()->create());

        $this->addProduct()->assertForbidden();
        $this->clearProduct()->assertForbidden();
        $this->handleImages()->assertForbidden();

        $this->assertProductHasNoDeal();
    }

    public function test_admin_can_put_a_product_on_a_deal_and_clear_it(): void
    {
        // A real bearer token, as the frontend sends after login.
        $this->withToken(User::factory()->admin()->create()->createToken('authToken')->plainTextToken);

        $this->addProduct()->assertCreated();

        $product = $this->product->fresh();
        $this->assertSame('black_friday', $product->special_deal_slug);
        $this->assertEquals(100, $product->discount);

        $this->clearProduct()->assertOk();

        $this->assertProductHasNoDeal();
    }

    public function test_admin_can_run_handle_images(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->handleImages()->assertOk();
    }
}
