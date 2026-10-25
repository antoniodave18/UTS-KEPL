<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskDayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $days = $request->user()->taskDays()
            ->withCount('tasks')
            ->orderByDesc('date')
            ->get();

        return response()->json($days);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $day = $request->user()->taskDays()->firstOrCreate(['date' => $data['date']]);

        return response()->json($day, $day->wasRecentlyCreated ? 201 : 200);
    }
}
