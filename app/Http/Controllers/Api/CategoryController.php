<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryCollection;
use App\Http\Traits\ApiResponseTrait;
use App\Services\CategoryService;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CategoryController extends Controller
{
    use AuthorizesRequests;
    use ApiResponseTrait;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        private CategoryService $categoryService
    ) {
        // Apply middleware for authorization
        
        // Only used with policies set up.
        // $this->authorizeResource(Category::class, 'category');
    }

    /**
     * Display a listing of categories.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $categories = $this->categoryService->getPaginated($perPage);

            return $this->successResponse(
                new CategoryCollection($categories),
                'Categories retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve categories',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Store a newly created category.
     *
     * @param CreateCategoryRequest $request
     * @return JsonResponse
     */
    public function store(CreateCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->categoryService->create($request->validated());

            return $this->successResponse(
                new CategoryResource($category),
                'Category created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create category',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Display the specified category.
     *
     * @param Category $category
     * @return JsonResponse
     */
    public function show(Category $category): JsonResponse
    {
        try {
            // Load relationships for detailed view
            $category->loadCount('articles');
            
            return $this->successResponse(
                new CategoryResource($category),
                'Category retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Category not found',
                404,
                $e->getMessage()
            );
        }
    }

    /**
     * Update the specified category.
     *
     * @param UpdateCategoryRequest $request
     * @param Category $category
     * @return JsonResponse
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        try {
            $updatedCategory = $this->categoryService->update($category, $request->validated());

            return $this->successResponse(
                new CategoryResource($updatedCategory),
                'Category updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update category',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Remove the specified category.
     *
     * @param Category $category
     * @return JsonResponse
     */
    public function destroy(Category $category): JsonResponse
    {
        try {
            $this->categoryService->delete($category);

            return $this->successResponse(
                null,
                'Category deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete category',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Activate the specified category.
     *
     * @param Category $category
     * @return JsonResponse
     */
    public function activate(Category $category): JsonResponse
    {
        try {
            $this->authorize('update', $category);
            
            $updatedCategory = $this->categoryService->activate($category);

            return $this->successResponse(
                new CategoryResource($updatedCategory),
                'Category activated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to activate category',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Deactivate the specified category.
     *
     * @param Category $category
     * @return JsonResponse
     */
    public function deactivate(Category $category): JsonResponse
    {
        try {
            $this->authorize('update', $category);
            
            $updatedCategory = $this->categoryService->deactivate($category);

            return $this->successResponse(
                new CategoryResource($updatedCategory),
                'Category deactivated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to deactivate category',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Get category statistics.
     *
     * @param Category $category
     * @return JsonResponse
     */
    public function stats(Category $category): JsonResponse
    {
        try {
            $this->authorize('view', $category);
            
            $stats = $this->categoryService->getStats($category);

            return $this->successResponse(
                $stats,
                'Category statistics retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve category statistics',
                500,
                $e->getMessage()
            );
        }
    }
}