<?php

namespace App\Http\Controllers;

use App\Http\Requests\WaterMeterRequest;
use App\Models\WaterMeter;
use App\Services\WaterMeterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaterMeterController extends Controller
{
    public function __construct(
        private readonly WaterMeterService $waterMeterService
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('water-meter/index', [
            'waterMeters' => $this->waterMeterService->paginate($search ?: null),
            'stats' => [
                'total' => WaterMeter::query()->count(),
                'active' => WaterMeter::query()->where('is_active', true)->count(),
                'inactive' => WaterMeter::query()->where('is_active', false)->count(),
                'with_bills' => WaterMeter::query()->has('waterBills')->count(),
            ],
            'consumers' => \App\Models\Consumer::query()
                ->orderBy('consumer_name')
                ->get(['consumer_id', 'account_number', 'consumer_name']),
            'filter' => ['search' => $search],
        ]);
    }

    public function store(WaterMeterRequest $request): RedirectResponse
    {
        $this->waterMeterService->create($request->validated());

        return back()->with('success', 'Water meter created successfully.');
    }

    public function show(WaterMeter $waterMeter)
    {
        return response()->json(
            $waterMeter->load(['consumer', 'waterBills.waterRate'])
        );
    }

    public function update(WaterMeterRequest $request, WaterMeter $waterMeter): RedirectResponse
    {
        $this->waterMeterService->update($waterMeter, $request->validated());

        return back()->with('success', 'Water meter updated successfully.');
    }

    public function destroy(WaterMeter $waterMeter): RedirectResponse
    {
        $this->waterMeterService->delete($waterMeter);

        return back()->with('success', 'Water meter deleted successfully.');
    }
}
