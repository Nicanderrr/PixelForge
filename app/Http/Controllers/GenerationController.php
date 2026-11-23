<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerationRequest;
use App\Models\Generation;
use App\Services\Pricing;
use App\Services\RequestGeneration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class GenerationController extends Controller
{
    public function dashboard(Request $request)
    {
        $query = Generation::where('user_id', $request->user()->id);

        return Inertia::render('Dashboard', [
            'recent' => (clone $query)->latest()->limit(4)->get(),
            'stats' => ['total' => (clone $query)->count(), 'succeeded' => (clone $query)->where('status', 'succeeded')->count(),
                'active' => (clone $query)->whereNotIn('status', ['succeeded', 'failed'])->count(),
                'spent' => (string) DB::table('generation_charges')->where('user_id', $request->user()->id)->sum('customer_charge')],
        ]);
    }

    public function create()
    {
        return Inertia::render('Create', ['requestKey' => (string) Str::uuid(), 'providerConfigured' => (bool) config('pixelforge.api_key')]);
    }

    public function estimate(GenerationRequest $request, Pricing $pricing)
    {
        try {
            return response()->json($pricing->estimate($request->prompt, $request->size, $request->quality, $request->reference_images));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Live pricing is unavailable. Please try again shortly.'], 503);
        }
    }

    public function store(GenerationRequest $request, RequestGeneration $service)
    {
        return to_route('generations.show', $service->create($request->user(), $request->validated()));
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'all');

        return Inertia::render('History', ['generations' => Generation::where('user_id', $request->user()->id)
            ->when(in_array($status, ['queued', 'processing', 'succeeded', 'failed', 'attention'], true), fn ($q) => $q->where('status', $status))
            ->latest()->paginate(12)->withQueryString(), 'filter' => $status]);
    }

    public function show(Request $request, Generation $generation)
    {
        abort_unless($generation->user_id === $request->user()->id, 404);

        return Inertia::render('Detail', ['generation' => $generation]);
    }
}
