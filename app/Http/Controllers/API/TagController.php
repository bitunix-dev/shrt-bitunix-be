<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index()
    {
        $tags = Tag::all();
        return response()->json(['status' => 200, 'data' => $tags], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:tags,name',
        ]);

        $tag = Tag::create(['name' => $request->name]);

        return response()->json(['status' => 201, 'data' => $tag, 'message' => 'Tag created successfully'], 201);
    }

    public function show($id)
    {
        $tag = Tag::find($id);

        if (!$tag) {
            return response()->json(['status' => 404, 'message' => 'Tag not found'], 404);
        }

        return response()->json(['status' => 200, 'data' => $tag], 200);
    }

    public function update(Request $request, $id)
    {
        $tag = Tag::find($id);

        if (!$tag) {
            return response()->json(['status' => 404, 'message' => 'Tag not found'], 404);
        }

        $request->validate([
            'name' => 'required|string|unique:tags,name,' . $id,
        ]);

        $tag->update(['name' => $request->name]);

        return response()->json(['status' => 200, 'data' => $tag, 'message' => 'Tag updated successfully'], 200);
    }

    public function destroy($id)
    {
        $tag = Tag::find($id);

        if (!$tag) {
            return response()->json(['status' => 404, 'message' => 'Tag not found'], 404);
        }

        $tag->delete();

        return response()->json(['status' => 200, 'message' => 'Tag deleted successfully'], 200);
    }
}