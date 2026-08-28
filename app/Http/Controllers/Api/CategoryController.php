<?php

namespace App\Http\Controllers\Api;

use App\Actions\Categories\CreateCategory;
use App\Actions\Categories\DeleteCategory;
use App\Actions\Categories\UpdateCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyCategoryRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Board;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function store(StoreCategoryRequest $request, Board $board, CreateCategory $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($request->user(), $board, $request->validated('name'), $request->validated('color'))], 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($request->user(), $category, $request->validated('name'), $request->validated('color'))]);
    }

    public function destroy(DestroyCategoryRequest $request, Category $category, DeleteCategory $action): JsonResponse
    {
        $action->execute($request->user(), $category);

        return response()->json(status: 204);
    }
}
