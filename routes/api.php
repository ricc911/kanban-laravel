<?php

use App\Http\Controllers\Api\BoardColumnController;
use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FolderController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::middleware('auth:sanctum')->scopeBindings()->group(function (): void {
    Route::post('workspaces', [WorkspaceController::class, 'store']);
    Route::post('workspaces/{workspace}/invitations', [InvitationController::class, 'store']);
    Route::post('invitations/{token}/accept', [InvitationController::class, 'accept']);

    Route::post('workspaces/{workspace}/folders', [FolderController::class, 'store']);
    Route::post('workspaces/{workspace}/boards', [BoardController::class, 'store']);

    Route::post('boards/{board}/columns', [BoardColumnController::class, 'store']);
    Route::patch('columns/{column}', [BoardColumnController::class, 'update']);
    Route::post('columns/{column}/move', [BoardColumnController::class, 'move']);
    Route::delete('columns/{column}', [BoardColumnController::class, 'destroy']);
    Route::post('boards/{board}/columns/reorder', [BoardColumnController::class, 'reorder']);

    Route::post('boards/{board}/categories', [CategoryController::class, 'store']);
    Route::patch('categories/{category}', [CategoryController::class, 'update']);
    Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

    Route::post('boards/{board}/columns/{column}/tasks', [TaskController::class, 'store']);
    Route::patch('tasks/{task}', [TaskController::class, 'update']);
    Route::post('tasks/{task}/move', [TaskController::class, 'move']);
    Route::delete('tasks/{task}', [TaskController::class, 'destroy']);
    Route::post('columns/{column}/tasks/reorder', [TaskController::class, 'reorder']);

    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });

    Route::get('workspaces', [WorkspaceController::class, 'index']);
    Route::get('workspaces/{workspace}/folders', [FolderController::class, 'index']);
    Route::get('workspaces/{workspace}/boards', [BoardController::class, 'index']);
    Route::get('boards/{board}', [BoardController::class, 'show']
    );

});

