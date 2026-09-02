<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\BoardColumnController;
use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FolderController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TaskAssigneeController;
use App\Http\Controllers\Api\TaskCommentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskEditingController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->scopeBindings()->group(function (): void {
    Route::post('workspaces', [WorkspaceController::class, 'store']);
    Route::patch('workspaces/{workspace}', [WorkspaceController::class, 'update']);
    Route::delete('workspaces/{workspace}', [WorkspaceController::class, 'destroy']);
    Route::post('workspaces/{workspace}/invitations', [InvitationController::class, 'store']);
    Route::get('workspaces/{workspace}/members', [WorkspaceController::class, 'members']);
    Route::get('workspaces/{workspace}/invitations', [WorkspaceController::class, 'invitations']);
    Route::get('workspaces/{workspace}/activity', [ActivityLogController::class, 'index']);
    Route::delete('workspaces/{workspace}/members/{member}', [WorkspaceController::class, 'removeMember'])->withoutScopedBindings();
    Route::patch('workspaces/{workspace}/members/{member}/role', [WorkspaceController::class, 'updateMemberRole'])->withoutScopedBindings();
    Route::delete('workspaces/{workspace}/leave', [WorkspaceController::class, 'leave']);
    Route::post('invitations/{token}/accept', [InvitationController::class, 'accept']);
    Route::get('invitations', [InvitationController::class, 'index']);
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('boards/{board}/ai/status', [AiController::class, 'status']);
    Route::post('tasks/{task}/ai/generate-description', [AiController::class, 'generateDescription'])->middleware('throttle:ai-generation');
    Route::post('boards/{board}/ai/breakdown', [AiController::class, 'breakdown'])->middleware('throttle:ai-generation');
    Route::post('boards/{board}/ai/summary', [AiController::class, 'summary'])->middleware('throttle:ai-generation');
    Route::post('boards/{board}/ai/analysis', [AiController::class, 'analysis'])->middleware('throttle:ai-generation');
    Route::post('boards/{board}/ai/breakdown/apply', [AiController::class, 'applyBreakdown']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::delete('invitations/{invitation}', [InvitationController::class, 'reject']);

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
    Route::post('tasks/{task}/editing-state', [TaskEditingController::class, 'update']);
    Route::get('tasks/{task}/comments', [TaskCommentController::class, 'index']);
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store']);
    Route::patch('task-comments/{comment}', [TaskCommentController::class, 'update']);
    Route::delete('task-comments/{comment}', [TaskCommentController::class, 'destroy']);
    Route::post('tasks/{task}/move', [TaskController::class, 'move']);
    Route::delete('tasks/{task}', [TaskController::class, 'destroy']);
    Route::post('tasks/{task}/assignees', [TaskAssigneeController::class, 'store']);
    Route::delete('tasks/{task}/assignees/{user}', [TaskAssigneeController::class, 'destroy'])->withoutScopedBindings();
    Route::post('columns/{column}/tasks/reorder', [TaskController::class, 'reorder']);

    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });
    Route::patch('account/profile', [AccountController::class, 'updateProfile']);
    Route::patch('account/password', [AccountController::class, 'updatePassword']);

    Route::get('workspaces', [WorkspaceController::class, 'index']);
    Route::get('workspaces/{workspace}/folders', [FolderController::class, 'index']);
    Route::get('workspaces/{workspace}/boards', [BoardController::class, 'index']);
    Route::get('boards/{board}', [BoardController::class, 'show']
    );

});
