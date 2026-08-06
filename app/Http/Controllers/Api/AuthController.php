<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     * Validates email/password against the users table and issues a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if ((int) $user->del_status !== 1 && $user->del_status !== 'Live') {
            return response()->json([
                'message' => 'This account is disabled.',
            ], 403);
        }

        // Tokens are not revoked here: the desktop app keeps one per install and
        // re-logins (e.g. test connection, credential change) must not kill the
        // token another instance is using. Logout revokes the current token.
        $token = $user->createToken('wpf-pos')->plainTextToken;

        $company = DB::table('companies')
            ->where('id', $user->company_id ?? 1)
            ->where('del_status', 'Live')
            ->first();

        $outletIds = $user->outlet_id
            ? array_filter(explode(',', $user->outlet_id))
            : [];

        $outlet = null;
        if (count($outletIds) > 0) {
            $outlet = DB::table('outlets')->whereIn('id', $outletIds)->first();
        }
        if (! $outlet) {
            $outlet = DB::table('outlets')->where('del_status', 'Live')->first();
        }

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'company' => $company
                ? ['id' => $company->id, 'name' => $company->name]
                : ['id' => 1, 'name' => 'Rashan Ki Dukan'],
            'outlet' => $outlet
                ? ['id' => $outlet->id, 'name' => $outlet->name]
                : ['id' => 1, 'name' => 'Main Outlet'],
            'server_time' => now()->toDateTimeString(),
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
