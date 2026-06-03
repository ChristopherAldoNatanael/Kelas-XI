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

        $deviceToken = DeviceToken::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'token' => $request->input('token'),
            ],
            [
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
