<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SpecialDeals;
use App\Models\User;
use App\Services\GeneralService;
use Cloudinary\Api\ApiResponse;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Media goes through the official Cloudinary SDK (bound in AppServiceProvider)
 * instead of the cloudinary-labs/cloudinary-laravel wrapper. These tests swap
 * the SDK for a mock so nothing ever reaches the real Cloudinary API, and pin
 * the file/options each call site sends and the response field it reads.
 */
class CloudinaryUploadsTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><rect width="1" height="1"/></svg>';

    private MockInterface $uploadApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uploadApi = Mockery::mock(UploadApi::class);

        $sdk = Mockery::mock(Cloudinary::class);
        $sdk->shouldReceive('uploadApi')->andReturn($this->uploadApi);

        $this->instance(Cloudinary::class, $sdk);
    }

    private function response(array $body): ApiResponse
    {
        return new ApiResponse($body, []);
    }

    public function test_sdk_is_built_from_the_cloudinary_config_as_a_singleton(): void
    {
        $this->app->forgetInstance(Cloudinary::class);
        config(['cloudinary.cloud_url' => 'cloudinary://123456:secret@demo-cloud']);

        $sdk = app(Cloudinary::class);

        $this->assertInstanceOf(Cloudinary::class, $sdk);
        $this->assertNotInstanceOf(MockInterface::class, $sdk);
        $this->assertSame($sdk, app(Cloudinary::class));
        $this->assertSame('demo-cloud', $sdk->configuration->cloud->cloudName);
        $this->assertSame('123456', $sdk->configuration->cloud->apiKey);
    }

    public function test_upload_media_sends_the_file_and_folder_and_returns_the_secure_url(): void
    {
        $this->uploadApi->shouldReceive('upload')
            ->once()
            ->with('/tmp/shirt.png', ['transformation' => [], 'folder' => 'Product'])
            ->andReturn($this->response(['secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/Product/shirt.png']));

        $url = GeneralService::uploadMedia('/tmp/shirt.png', 'Product');

        $this->assertSame('https://res.cloudinary.com/demo/image/upload/v1/Product/shirt.png', $url);
    }

    public function test_upload_media_keeps_resizing_and_video_options_for_gifs(): void
    {
        $this->uploadApi->shouldReceive('upload')
            ->once()
            ->with('/tmp/promo.gif', [
                'transformation' => [['width' => 300, 'height' => 200, 'crop' => 'scale']],
                'folder' => 'Deals',
                'resource_type' => 'video',
                'format' => 'gif',
            ])
            ->andReturn($this->response(['secure_url' => 'https://res.cloudinary.com/demo/video/upload/v1/Deals/promo.gif']));

        $url = GeneralService::uploadMedia('/tmp/promo.gif', 'Deals', 'gif', 300, 200);

        $this->assertSame('https://res.cloudinary.com/demo/video/upload/v1/Deals/promo.gif', $url);
    }

    public function test_upload_media_returns_an_empty_string_without_a_secure_url(): void
    {
        $this->uploadApi->shouldReceive('upload')->once()->andReturn($this->response([]));

        $this->assertSame('', GeneralService::uploadMedia('/tmp/shirt.png', 'Product'));
    }

    public function test_admin_product_store_uploads_each_image_and_saves_its_secure_url(): void
    {
        $file = UploadedFile::fake()->createWithContent('shirt.png', base64_decode(self::PNG_1X1));
        $secureUrl = 'https://res.cloudinary.com/demo/image/upload/v1/Product/shirt.png';

        $this->uploadApi->shouldReceive('upload')
            ->once()
            ->with($file->getRealPath(), ['transformation' => [], 'folder' => 'Product'])
            ->andReturn($this->response(['secure_url' => $secureUrl]));

        $this->actingAs(User::factory()->admin()->create())
            ->post('/api/v1/admin/products', [
                'name' => 'Linen Shirt',
                'description' => 'A shirt',
                'category_id' => Category::factory()->create()->id,
                'material' => 'Linen',
                'price' => 25,
                'images' => [['file' => $file, 'color_id' => '']],
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame($secureUrl, ProductImage::sole()->image_path);
    }

    /**
     * Laravel 12 dropped SVG from the plain `image` rule. Product images
     * accepted SVG before the upgrade, so the rule now opts back in with
     * `image:allow_svg`.
     */
    public function test_admin_product_store_still_accepts_svg_images(): void
    {
        $file = UploadedFile::fake()->createWithContent('logo.svg', self::SVG);

        $this->uploadApi->shouldReceive('upload')
            ->once()
            ->with($file->getRealPath(), ['transformation' => [], 'folder' => 'Product'])
            ->andReturn($this->response(['secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/Product/logo.svg']));

        $this->actingAs(User::factory()->admin()->create())
            ->post('/api/v1/admin/products', [
                'name' => 'Logo Tee',
                'description' => 'A tee',
                'category_id' => Category::factory()->create()->id,
                'material' => 'Cotton',
                'price' => 15,
                'images' => [['file' => $file, 'color_id' => '']],
            ], ['Accept' => 'application/json'])
            ->assertCreated();
    }

    public function test_deal_type_image_upload_saves_the_secure_url(): void
    {
        $file = UploadedFile::fake()->createWithContent('banner.png', base64_decode(self::PNG_1X1));

        $this->uploadApi->shouldReceive('upload')
            ->once()
            ->with($file->getRealPath(), ['transformation' => [], 'folder' => 'Deals'])
            ->andReturn($this->response(['secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/Deals/banner.png']));

        // A real bearer token, as the frontend sends after login (AuthController
        // issues 'authToken'), so this goes through Optional + auth:sanctum + Admin.
        $token = User::factory()->admin()->create()->createToken('authToken')->plainTextToken;

        $this->withToken($token)
            ->post('/api/v1/user/deals/create-deal-type', [
                'deal_type' => 'Black Friday',
                'visibility' => 'visible',
                'image' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertSame('https://res.cloudinary.com/demo/image/upload/v1/Deals/banner.png', SpecialDeals::sole()->image);
    }

    /**
     * Deal-type banners accept SVG again (as before the upgrade) now that only
     * admins can upload them, the same as admin product images.
     */
    public function test_admin_deal_type_image_upload_accepts_svg(): void
    {
        $file = UploadedFile::fake()->createWithContent('banner.svg', self::SVG);

        $this->uploadApi->shouldReceive('upload')
            ->once()
            ->with($file->getRealPath(), ['transformation' => [], 'folder' => 'Deals'])
            ->andReturn($this->response(['secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/Deals/banner.svg']));

        $this->actingAs(User::factory()->admin()->create())
            ->post('/api/v1/user/deals/create-deal-type', [
                'deal_type' => 'Black Friday',
                'visibility' => 'visible',
                'image' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertSame('https://res.cloudinary.com/demo/image/upload/v1/Deals/banner.svg', SpecialDeals::sole()->image);
    }

    /**
     * create-deal-type and delete-deal-type keep their /user/deals URLs for the
     * frontend, but sit behind auth:sanctum + Admin like /admin/products.
     */
    public function test_deal_type_writes_are_refused_for_guests_and_non_admins(): void
    {
        $deal = SpecialDeals::create(['deal_type' => 'Black Friday', 'slug' => 'black_friday']);
        $create = ['deal_type' => 'Cyber Monday', 'image' => UploadedFile::fake()->createWithContent('b.png', base64_decode(self::PNG_1X1))];

        $this->uploadApi->shouldNotReceive('upload');
        $this->uploadApi->shouldNotReceive('destroy');

        $this->postJson('/api/v1/user/deals/create-deal-type', $create)->assertUnauthorized();
        $this->deleteJson("/api/v1/user/deals/delete-deal-type/{$deal->id}")->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->postJson('/api/v1/user/deals/create-deal-type', $create)->assertForbidden();
        $this->deleteJson("/api/v1/user/deals/delete-deal-type/{$deal->id}")->assertForbidden();

        $this->assertSame([$deal->id], SpecialDeals::pluck('id')->all());
    }

    public function test_admin_product_delete_destroys_each_image_by_public_id(): void
    {
        $product = Product::create([
            'category_id' => Category::factory()->create()->id,
            'name' => 'Linen Shirt',
            'description' => 'A shirt',
            'material' => 'Linen',
            'price' => 25,
        ]);
        $product->images()->create(['image_path' => 'https://res.cloudinary.com/demo/image/upload/v1712345678/Product/abc123.jpg']);

        $this->uploadApi->shouldReceive('destroy')
            ->once()
            ->with('Product/abc123')
            ->andReturn($this->response(['result' => 'ok']));

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/v1/admin/products/{$product->id}")
            ->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_deleting_a_deal_type_destroys_its_image_by_public_id(): void
    {
        $deal = SpecialDeals::create([
            'deal_type' => 'Black Friday',
            'slug' => 'black_friday',
            'image' => 'https://res.cloudinary.com/demo/image/upload/v1712345678/Deals/bf-banner.png',
        ]);

        $this->uploadApi->shouldReceive('destroy')
            ->once()
            ->with('Deals/bf-banner')
            ->andReturn($this->response(['result' => 'ok']));

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/v1/user/deals/delete-deal-type/{$deal->id}")
            ->assertOk();

        $this->assertDatabaseMissing('special_deals', ['id' => $deal->id]);
    }
}
