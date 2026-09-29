<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'MacBook Pro',
            'sku' => 'MBP-001',
            'description' => '14-inch MacBook Pro',
            'category_id' => Category::factory()->create()->id,
            'price' => '7999.00',
            'stock' => 10,
            'supplier_ids' => Supplier::factory()->count(2)->create()->modelKeys(),
        ], $overrides);
    }

    public function test_all_product_endpoints_require_authentication_even_without_accept_header(): void
    {
        $product = Product::factory()->create();

        foreach ([['GET', '/api/products'], ['POST', '/api/products'],
            ['GET', "/api/products/{$product->id}"], ['PUT', "/api/products/{$product->id}"],
            ['PATCH', "/api/products/{$product->id}"], ['DELETE', "/api/products/{$product->id}"]] as [$method, $uri]) {
            $this->call($method, $uri)->assertUnauthorized()->assertJsonStructure(['message']);
        }
    }

    public function test_authenticated_user_can_list_paginated_products_with_relationships(): void
    {
        $this->authenticate();
        $products = Product::factory()->count(4)->hasAttached(Supplier::factory())->create();

        $this->getJson('/api/products?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('data.0.id', $products[2]->id)
            ->assertJsonStructure(['data' => [['category' => ['id', 'name'], 'suppliers' => [['id', 'name']]]], 'links', 'meta']);
    }

    public function test_authenticated_user_can_create_product_with_suppliers(): void
    {
        $this->authenticate();
        $payload = $this->payload();

        $response = $this->postJson('/api/products', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'MacBook Pro')
            ->assertJsonPath('data.price', '7999.00')
            ->assertJsonPath('data.stock_status', 'in_stock')
            ->assertJsonPath('data.category.id', $payload['category_id'])
            ->assertJsonCount(2, 'data.suppliers');

        $this->assertDatabaseHas('products', ['id' => $response->json('data.id'), 'sku' => 'MBP-001']);
        foreach ($payload['supplier_ids'] as $supplierId) {
            $this->assertDatabaseHas('product_supplier', ['product_id' => $response->json('data.id'), 'supplier_id' => $supplierId]);
        }
    }

    public function test_creation_rejects_missing_required_fields(): void
    {
        $this->authenticate();
        $this->postJson('/api/products', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'sku', 'category_id', 'price', 'stock']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_creation_rejects_invalid_fields_without_writing_anything(): void
    {
        $this->authenticate();
        $this->postJson('/api/products', [
            'name' => str_repeat('a', 256), 'sku' => str_repeat('a', 101),
            'description' => [], 'category_id' => 99999, 'price' => -1,
            'stock' => -1, 'supplier_ids' => [99999],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'name', 'sku', 'description', 'category_id', 'price', 'stock', 'supplier_ids.0',
        ]);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_supplier', 0);
    }

    public function test_creation_rejects_duplicate_sku_and_supplier_ids(): void
    {
        $this->authenticate();
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->postJson('/api/products', $this->payload([
            'sku' => $product->sku, 'supplier_ids' => [$supplier->id, $supplier->id],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['sku', 'supplier_ids.0']);
    }

    public function test_creation_allows_zero_price_zero_stock_and_optional_fields(): void
    {
        $this->authenticate();
        $payload = $this->payload(['price' => 0, 'stock' => 0, 'description' => null]);
        unset($payload['supplier_ids']);
        $this->postJson('/api/products', $payload)->assertCreated()
            ->assertJsonPath('data.price', '0.00')
            ->assertJsonPath('data.stock_status', 'out_of_stock')
            ->assertJsonPath('data.description', null)
            ->assertJsonCount(0, 'data.suppliers');
    }

    public function test_price_precision_and_storage_limits_are_validated(): void
    {
        $this->authenticate();
        $this->postJson('/api/products', $this->payload(['price' => '12.345', 'stock' => '1.5']))
            ->assertUnprocessable()->assertJsonValidationErrors(['price', 'stock']);
        $this->postJson('/api/products', $this->payload(['price' => '10000000000', 'stock' => '4294967296']))
            ->assertUnprocessable()->assertJsonValidationErrors(['price', 'stock']);
    }

    public function test_authenticated_user_can_view_product(): void
    {
        $this->authenticate();
        $product = Product::factory()->hasAttached(Supplier::factory())->create(['stock' => 0]);
        $this->getJson("/api/products/{$product->id}")->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.stock_status', 'out_of_stock')
            ->assertJsonPath('data.category.id', $product->category_id)
            ->assertJsonCount(1, 'data.suppliers');
        $this->getJson('/api/products/99999')->assertNotFound();
    }

    public function test_put_updates_product_and_replaces_supplier_relationships(): void
    {
        $this->authenticate();
        $product = Product::factory()->hasAttached(Supplier::factory())->create();
        $oldSupplier = $product->suppliers->first();
        $payload = $this->payload(['sku' => $product->sku]);

        $this->putJson("/api/products/{$product->id}", $payload)->assertOk()
            ->assertJsonPath('data.name', 'MacBook Pro')
            ->assertJsonPath('data.category.id', $payload['category_id'])
            ->assertJsonPath('data.price', '7999.00')
            ->assertJsonCount(2, 'data.suppliers');
        $this->assertDatabaseMissing('product_supplier', ['product_id' => $product->id, 'supplier_id' => $oldSupplier->id]);
        $this->assertEqualsCanonicalizing($payload['supplier_ids'], $product->suppliers()->pluck('suppliers.id')->all());
        $this->assertSame($payload['name'], $product->fresh()->name);
    }

    public function test_patch_preserves_omitted_fields_and_suppliers_and_can_clear_suppliers(): void
    {
        $this->authenticate();
        $product = Product::factory()->hasAttached(Supplier::factory())->create();
        $supplierId = $product->suppliers->first()->id;

        $this->patchJson("/api/products/{$product->id}", ['stock' => 0])->assertOk()
            ->assertJsonPath('data.name', $product->name)
            ->assertJsonPath('data.stock_status', 'out_of_stock')
            ->assertJsonPath('data.suppliers.0.id', $supplierId);
        $this->patchJson("/api/products/{$product->id}", ['supplier_ids' => [], 'description' => null])->assertOk()
            ->assertJsonCount(0, 'data.suppliers')->assertJsonPath('data.description', null);
        $this->assertDatabaseMissing('product_supplier', ['product_id' => $product->id]);
    }

    public function test_invalid_update_is_rejected_without_changing_product_or_suppliers(): void
    {
        $this->authenticate();
        $product = Product::factory()->hasAttached(Supplier::factory())->create();
        $other = Product::factory()->create();
        $this->patchJson("/api/products/{$product->id}", [
            'name' => 'Changed', 'sku' => $other->sku, 'stock' => null, 'supplier_ids' => [99999],
        ])->assertUnprocessable()->assertJsonValidationErrors(['sku', 'stock', 'supplier_ids.0']);
        $this->assertSame($product->name, $product->fresh()->name);
        $this->assertSame(1, $product->suppliers()->count());
    }

    public function test_delete_soft_deletes_product_and_hides_it_from_normal_endpoints(): void
    {
        $this->authenticate();
        $product = Product::factory()->hasAttached(Supplier::factory())->create();
        $this->deleteJson("/api/products/{$product->id}")->assertNoContent();
        $this->assertSoftDeleted($product);
        $this->assertDatabaseHas('product_supplier', ['product_id' => $product->id]);
        $this->getJson('/api/products')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/products/{$product->id}")->assertNotFound();
        $this->patchJson("/api/products/{$product->id}", ['stock' => 1])->assertNotFound();
        $this->deleteJson("/api/products/{$product->id}")->assertNotFound();
        $this->postJson('/api/products', $this->payload(['sku' => $product->sku]))
            ->assertUnprocessable()->assertJsonValidationErrors(['sku']);
    }

    public function test_filtering_by_category(): void
    {
        $this->authenticate();
        $product = Product::factory()->create();
        Product::factory()->create();
        $this->getJson('/api/products?category_id='.$product->category_id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id);
    }

    public function test_price_range_is_inclusive(): void
    {
        $this->authenticate();
        foreach ([99, 100, 200, 201] as $price) {
            Product::factory()->create(['price' => $price]);
        }
        $this->getJson('/api/products?min_price=100&max_price=200')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.price', '100.00')->assertJsonPath('data.1.price', '200.00');
    }

    public function test_stock_range_is_inclusive_and_zero_is_a_valid_filter(): void
    {
        $this->authenticate();
        foreach ([0, 1, 10, 11] as $stock) {
            Product::factory()->create(['stock' => $stock]);
        }
        $this->getJson('/api/products?min_stock=1&max_stock=10')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/products?min_stock=0&max_stock=0')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.stock', 0);
    }

    public function test_filters_work_together_and_are_preserved_in_pagination_links(): void
    {
        $this->authenticate();
        $category = Category::factory()->create();
        $matches = Product::factory()->count(2)->for($category)->create(['price' => 100, 'stock' => 2]);
        Product::factory()->for($category)->create(['price' => 99, 'stock' => 2]);
        Product::factory()->for($category)->create(['price' => 101, 'stock' => 2]);
        Product::factory()->for($category)->create(['price' => 100, 'stock' => 0]);
        Product::factory()->for($category)->create(['price' => 100, 'stock' => 4]);
        Product::factory()->create(['price' => 100, 'stock' => 2]);
        $query = http_build_query(['category_id' => $category->id, 'min_price' => 100, 'max_price' => 100,
            'min_stock' => 1, 'max_stock' => 3, 'per_page' => 1]);
        $response = $this->getJson('/api/products?'.$query)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $matches[0]->id)->assertJsonPath('meta.total', 2);
        $this->assertStringContainsString('category_id='.$category->id, $response->json('links.next'));
        $this->assertStringContainsString('min_price=100', $response->json('links.next'));
        $this->getJson($response->json('links.next'))->assertOk()->assertJsonPath('data.0.id', $matches[1]->id);
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_return_validation_errors(string $query, string $field): void
    {
        $this->authenticate();
        $this->getJson('/api/products?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function invalidFilters(): array
    {
        return [
            ['per_page=101', 'per_page'], ['per_page=0', 'per_page'], ['page=0', 'page'],
            ['category_id=99999', 'category_id'], ['min_price=-1', 'min_price'],
            ['min_price=20&max_price=10', 'max_price'], ['min_stock=5&max_stock=4', 'max_stock'],
            ['min_stock=1.5', 'min_stock'], ['max_stock[]=1', 'max_stock'],
        ];
    }
}
