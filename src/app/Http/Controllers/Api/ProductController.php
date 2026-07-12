<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\ProductService;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends ApiController
{
    public function __construct(
        private readonly ProductService $productService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return ProductResource::collection(
            $this->productService->paginate($request->query()),
        )->additional([
            'success' => true,
            'message' => 'Daftar produk berhasil diambil.',
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($request->validated());

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
        $product = $this->productService->update(
            $product,
            $request->validated(),
        );

        return $this->success(
            new ProductResource($product),
            'Produk berhasil diperbarui.',
        );
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->productService->delete($product);

        return $this->noContent('Produk berhasil dihapus.');
    }
}
