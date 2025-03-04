<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Medium;
use Illuminate\Http\Request;

class MediumController extends Controller
{
    public function index()
    {
        $mediums = Medium::all();
        return response()->json(['status' => 200, 'data' => $mediums], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:mediums,name',
        ]);

        $medium = Medium::create(['name' => $request->name]);

        return response()->json(['status' => 201, 'data' => $medium, 'message' => 'Medium created successfully'], 201);
    }

    public function show($id)
    {
        $medium = Medium::find($id);

        if (!$medium) {
            return response()->json(['status' => 404, 'message' => 'Medium not found'], 404);
        }

        return response()->json(['status' => 200, 'data' => $medium], 200);
    }

    public function update(Request $request, $id)
    {
        $medium = Medium::find($id);

        if (!$medium) {
            return response()->json(['status' => 404, 'message' => 'Medium not found'], 404);
        }

        $request->validate([
            'name' => 'required|string|unique:mediums,name,' . $id,
        ]);

        $medium->update(['name' => $request->name]);

        return response()->json(['status' => 200, 'data' => $medium, 'message' => 'Medium updated successfully'], 200);
    }

    public function destroy($id)
    {
        $medium = Medium::find($id);

        if (!$medium) {
            return response()->json(['status' => 404, 'message' => 'Medium not found'], 404);
        }

        $medium->delete();

        return response()->json(['status' => 200, 'message' => 'Medium deleted successfully'], 200);
    }
}