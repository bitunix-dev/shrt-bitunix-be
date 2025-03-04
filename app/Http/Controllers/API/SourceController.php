<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Source;
use Illuminate\Http\Request;

class SourceController extends Controller
{
    public function index()
    {
        $sources = Source::all();
        return response()->json(['status' => 200, 'data' => $sources], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:sources,name',
        ]);

        $source = Source::create(['name' => $request->name]);

        return response()->json(['status' => 201, 'data' => $source, 'message' => 'Source created successfully'], 201);
    }

    public function show($id)
    {
        $source = Source::find($id);

        if (!$source) {
            return response()->json(['status' => 404, 'message' => 'Source not found'], 404);
        }

        return response()->json(['status' => 200, 'data' => $source], 200);
    }

    public function update(Request $request, $id)
    {
        $source = Source::find($id);

        if (!$source) {
            return response()->json(['status' => 404, 'message' => 'Source not found'], 404);
        }

        $request->validate([
            'name' => 'required|string|unique:sources,name,' . $id,
        ]);

        $source->update(['name' => $request->name]);

        return response()->json(['status' => 200, 'data' => $source, 'message' => 'Source updated successfully'], 200);
    }

    public function destroy($id)
    {
        $source = Source::find($id);

        if (!$source) {
            return response()->json(['status' => 404, 'message' => 'Source not found'], 404);
        }

        $source->delete();

        return response()->json(['status' => 200, 'message' => 'Source deleted successfully'], 200);
    }
}