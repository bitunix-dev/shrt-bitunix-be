<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Medium;
use Illuminate\Http\Request;

class MediumController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $mediums = Medium::all();
        return response()->json(['data' => $mediums], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:mediums,name',
        ]);

        $medium = Medium::create([
            'name' => $request->name,
        ]);

        return response()->json(['data' => $medium, 'message' => 'Medium created successfully'], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $medium = Medium::findOrFail($id);
        return response()->json(['data' => $medium], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $medium = Medium::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:mediums,name,' . $id,
        ]);

        $medium->update([
            'name' => $request->name,
        ]);

        return response()->json(['data' => $medium, 'message' => 'Medium updated successfully'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $medium = Medium::findOrFail($id);
        $medium->delete();
        return response()->json(['message' => 'Medium deleted successfully'], 200);
    }
}