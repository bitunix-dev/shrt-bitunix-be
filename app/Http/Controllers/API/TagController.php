<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tags = Tag::all();
        return response()->json(['data' => $tags], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:tags,name',
        ]);

        $tag = Tag::create([
            'name' => $request->name,
        ]);

        return response()->json(['data' => $tag, 'message' => 'Tag created successfully'], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $tag = Tag::findOrFail($id);
        return response()->json(['data' => $tag], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $tag = Tag::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:tags,name,' . $id,
        ]);

        $tag->update([
            'name' => $request->name,
        ]);

        return response()->json(['data' => $tag, 'message' => 'Tag updated successfully'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $tag = Tag::findOrFail($id);
        $tag->delete();
        return response()->json(['message' => 'Tag deleted successfully'], 200);
    }
}