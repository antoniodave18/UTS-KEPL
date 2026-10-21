<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    // Semua query dibatasi ke task milik user login
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->tasks()->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        return response()->json($request->user()->tasks()->create($data), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($request->user()->tasks()->findOrFail($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $task = $request->user()->tasks()->findOrFail($id);

        $data = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_done'     => 'sometimes|boolean',
        ]);

        $task->update($data);

        return response()->json($task);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->tasks()->findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}