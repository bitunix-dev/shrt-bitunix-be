<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Source;
use Illuminate\Http\Request;

class SourceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sources = Source::all();
        return response()->json(['data' => $sources], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:sources,name',
        ]);

        $source = Source::create([
            'name' => $request->name,
        ]);

        return response()->json(['data' => $source, 'message' => 'Source created successfully'], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $source = Source::findOrFail($id);
        return response()->json(['data' => $source], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $source = Source::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:sources,name,' . $id,
        ]);

        $source->update([
            'name' => $request->name,
        ]);

        return response()->json(['data' => $source, 'message' => 'Source updated successfully'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $source = Source::findOrFail($id);
        $source->delete();
        return response()->json(['message' => 'Source deleted successfully'], 200);
    }
}