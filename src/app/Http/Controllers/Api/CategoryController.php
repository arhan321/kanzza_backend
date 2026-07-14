<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class CategoryController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        return CategoryResource::collection(
            Category::query()
                ->withCount('products')
                ->filter($request->query())
                ->orderBy('name')
                ->paginate($perPage)
                ->withQueryString(),
        )->additional([
            'success' => true,
            'message' => 'Daftar kategori berhasil diambil.',
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $category = Category::query()->create($data);

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
        $data = $request->validated();

        if (array_key_exists('name', $data) && ! array_key_exists('slug', $data)) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);
        $category->refresh();

        return $this->success(
            new CategoryResource($category),
            'Kategori berhasil diperbarui.',
        );
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return $this->noContent('Kategori berhasil dihapus.');
    }
}
