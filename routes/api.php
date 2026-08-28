<?php

use App\Http\Controllers\Api\BoardColumnController;
use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FolderController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->scopeBindings()->group(function (): void {
    Route::post('workspaces', [WorkspaceController::class, 'store']);
    Route::post('workspaces/{workspace}/invitations', [InvitationController::class, 'store']);
    Route::post('invitations/{token}/accept', [InvitationController::class, 'accept']);

    Route::post('workspaces/{workspace}/folders', [FolderController::class, 'store']);
    Route::patch('folders/{folder}', [FolderController::class, 'update']);
    Route::post('folders/{folder}/move', [FolderController::class, 'move']);
    Route::post('folders/{folder}/archive', [FolderController::class, 'archive']);
    Route::delete('folders/{folder}', [FolderController::class, 'destroy']);
    Route::post('workspaces/{workspace}/boards', [BoardController::class, 'store']);
    Route::patch('boards/{board}', [BoardController::class, 'update']);
    Route::post('boards/{board}/move', [BoardController::class, 'move']);
    Route::post('boards/{board}/archive', [BoardController::class, 'archive']);
    Route::delete('boards/{board}', [BoardController::class, 'destroy']);

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
