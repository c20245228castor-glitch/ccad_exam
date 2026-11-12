<?php

namespace App\Http\Controllers;

use App\Http\Requests\WaterRateRequest;
use App\Models\WaterRate;
use App\Services\WaterRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaterRateController extends Controller
{
    public function __construct(
        private readonly WaterRateService $waterRateService
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $today = today()->toDateString();

        return Inertia::render('water-rate/index', [
            'waterRates' => $this->waterRateService->paginate($search ?: null),
            'stats' => [
                'total' => WaterRate::query()->count(),
                'active' => WaterRate::query()
                    ->whereDate('effective_from', '<=', $today)
                    ->where(function ($query) use ($today) {
                        $query->whereNull('effective_to')
                            ->orWhereDate('effective_to', '>=', $today);
                    })
                    ->count(),
                'expired' => WaterRate::query()
                    ->whereNotNull('effective_to')
                    ->whereDate('effective_to', '<', $today)
                    ->count(),
                'upcoming' => WaterRate::query()
                    ->whereDate('effective_from', '>', $today)
                    ->count(),
            ],
            'filter' => ['search' => $search],
        ]);
    }

    public function store(WaterRateRequest $request): RedirectResponse
    {
        $this->waterRateService->create($request->validated());

        return back()->with('success', 'Water rate created successfully.');
    }

    public function show(WaterRate $waterRate)
    {
        return response()->json($waterRate->loadCount('waterBills'));
    }

    public function update(WaterRateRequest $request, WaterRate $waterRate): RedirectResponse
    {
        $this->waterRateService->update($waterRate, $request->validated());

        return back()->with('success', 'Water rate updated successfully.');
    }

    public function destroy(WaterRate $waterRate): RedirectResponse
    {
        $this->waterRateService->delete($waterRate);

        return back()->with('success', 'Water rate deleted successfully.');
    }
}
