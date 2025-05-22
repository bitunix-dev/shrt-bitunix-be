<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\VipCode;
use Illuminate\Http\Request;

class VipCodeController extends Controller
{
    public function index()
    {
        $vipCodes = VipCode::all();
        return response()->json(['status' => 200, 'data' => $vipCodes], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'partner_name' => 'required|string|max:255',
            'partner_code' => 'required|string|unique:vip_codes,partner_code|max:255',
        ]);

        $vipCode = VipCode::create([
            'partner_name' => $request->partner_name,
            'partner_code' => $request->partner_code
        ]);

        return response()->json([
            'status' => 201,
            'data' => $vipCode,
            'message' => 'VIP Code created successfully'
        ], 201);
    }

    public function show($id)
    {
        $vipCode = VipCode::find($id);

        if (!$vipCode) {
            return response()->json(['status' => 404, 'message' => 'VIP Code not found'], 404);
        }

        return response()->json(['status' => 200, 'data' => $vipCode], 200);
    }

    public function update(Request $request, $id)
    {
        $vipCode = VipCode::find($id);

        if (!$vipCode) {
            return response()->json(['status' => 404, 'message' => 'VIP Code not found'], 404);
        }

        $request->validate([
            'partner_name' => 'required|string|max:255',
            'partner_code' => 'required|string|unique:vip_codes,partner_code,' . $id . '|max:255',
        ]);

        $vipCode->update([
            'partner_name' => $request->partner_name,
            'partner_code' => $request->partner_code
        ]);

        return response()->json([
            'status' => 200,
            'data' => $vipCode,
            'message' => 'VIP Code updated successfully'
        ], 200);
    }

    public function destroy($id)
    {
        $vipCode = VipCode::find($id);

        if (!$vipCode) {
            return response()->json(['status' => 404, 'message' => 'VIP Code not found'], 404);
        }

        $vipCode->delete();

        return response()->json([
            'status' => 200,
            'message' => 'VIP Code deleted successfully'
        ], 200);
    }
}
