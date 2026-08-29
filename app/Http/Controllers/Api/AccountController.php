<?php

namespace App\Http\Controllers\Api;

use App\Actions\Account\UpdatePassword;
use App\Actions\Account\UpdateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function updateProfile(UpdateProfileRequest $request, UpdateProfile $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($request->user(), $request->validated('name'))]);
    }

    public function updatePassword(UpdatePasswordRequest $request, UpdatePassword $action): JsonResponse
    {
        $action->execute($request->user(), $request->validated('current_password'), $request->validated('password'));

        return response()->json(['message' => 'Password aggiornata.']);
    }
}
