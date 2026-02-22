<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController
{
    /**
     * Check if the user is authenticated (statelessly via session if available, or just return false).
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(data: [
            'is_logged_in' => $user !== null,
            'user' => $user ? [
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
        ]);
    }
}
