<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        return ProductResource::collection(
            Product::query()
                ->with('category')
                ->filter($request->query())
                ->latest()
                ->paginate($perPage)
                ->withQueryString(),
        )->additional([
            'success' => true,
            'message' => 'Daftar produk berhasil diambil.',
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        if (($data['image'] ?? null) instanceof UploadedFile) {
            $data['image'] = $data['image']->store('products', 'public');
        }

        $product = Product::query()->create($data)->load('category');

        return $this->success(
            new ProductResource($product),
            'Produk berhasil dibuat.',
            201,
        );
    }

    public function show(Product $product): JsonResponse
    {
        return $this->success(
            new ProductResource($product->load('category')),
            'Detail produk berhasil diambil.',
        );
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
    ): JsonResponse {
        $data = $request->validated();

        if (array_key_exists('name', $data) && ! array_key_exists('slug', $data)) {
            $data['slug'] = Str::slug($data['name']);
        }

        if (($data['image'] ?? null) instanceof UploadedFile) {
            if ($product->image !== null) {
                Storage::disk('public')->delete($product->image);
            }

            $data['image'] = $data['image']->store('products', 'public');
        } else {
            unset($data['image']);
        }

        $product->update($data);
        $product->refresh()->load('category');

        return $this->success(
            new ProductResource($product),
            'Produk berhasil diperbarui.',
        );
    }

    public function destroy(Product $product): JsonResponse
    {
        if ($product->image !== null) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return $this->noContent('Produk berhasil dihapus.');
    }
}
