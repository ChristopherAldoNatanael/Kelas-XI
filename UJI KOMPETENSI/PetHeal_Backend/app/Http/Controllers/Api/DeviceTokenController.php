<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * Register or update device token
     */
    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'device_type' => 'nullable|in:android,ios,web',
        ]);

        // PHASE 3 (F-05): token strings are the lookup key. Refuse to steal
        // a token already owned by another user (device handover happens via
        // login sync, not via explicit registration). Additive 409 case.
        $existing = DeviceToken::where('token', $request->input('token'))->first();
        if ($existing && (int) $existing->user_id !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Device token already registered to another account.',
            ], 409);
        }

        $deviceToken = DeviceToken::updateOrCreate(
            ['token' => $request->input('token')],
            [
                'user_id' => $request->user()->id,
                'device_type' => $request->input('device_type', 'android'),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Device token registered successfully',
            'data' => $deviceToken,
        ]);
    }

    /**
     * Remove device token (on logout)
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $request->input('token'))
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device token removed successfully',
        ]);
    }
}
