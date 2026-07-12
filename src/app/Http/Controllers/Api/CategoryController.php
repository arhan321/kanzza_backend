<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\CategoryService;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends ApiController
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            $this->categoryService->paginate($request->query()),
        )->additional([
            'success' => true,
            'message' => 'Daftar kategori berhasil diambil.',
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());

        return $this->success(
            new CategoryResource($category),
            'Kategori berhasil dibuat.',
            201,
        );
    }

    public function show(Category $category): JsonResponse
    {
        return $this->success(
            new CategoryResource($category->loadCount('products')),
            'Detail kategori berhasil diambil.',
        );
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category,
    ): JsonResponse {
        $category = $this->categoryService->update(
            $category,
            $request->validated(),
        );

        return $this->success(
            new CategoryResource($category),
            'Kategori berhasil diperbarui.',
        );
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->categoryService->delete($category);

        return $this->noContent('Kategori berhasil dihapus.');
    }
}
