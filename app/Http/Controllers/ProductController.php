<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(IndexProductRequest $request)
    {
        $filters = $request->validated();
        $products = Product::with(['category', 'suppliers'])
            ->filter($filters)
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 15)
            ->appends($filters);

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request)
    {
        // Reason why DB::transaction is prevent dirty data if either one query having error.
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($request->safe()->except('supplier_ids'));
            $product->suppliers()->sync($request->validated('supplier_ids', []));

            return $product;
        });

        return new ProductResource($product->load(['category', 'suppliers']));
    }

    public function show(Product $product)
    {
        return new ProductResource($product->load(['category', 'suppliers']));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        DB::transaction(function () use ($request, $product) {
            $product->update($request->safe()->except('supplier_ids'));

            if (array_key_exists('supplier_ids', $request->validated())) {
                $product->suppliers()->sync($request->validated('supplier_ids'));
            }
        });

        return new ProductResource($product->load(['category', 'suppliers']));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->noContent();
    }
}
