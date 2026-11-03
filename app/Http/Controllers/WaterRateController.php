<?php

namespace  App\Http\Controllers;

use App\Http\Requests\WaterRateRequest;
use App\Models\WaterRate;
use App\Services\WaterRateService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaterRateController extends Controller
{
    public function __construct(
        private readonly WaterRateService $waterRateService
    ) {}

    public function index(Request $req): Response
    {
        $search = $req->input('search');

        $stats = [
            'total' => WaterRate::query()->count(),
            'active' => WaterRate::query()
                ->where(function ($query) {
                    $query->whereNull('effective_to')
                        ->orWhere('effective_to', '>=', now()->toDateString());
                })
                ->count(),
            'expired' => WaterRate::query()
                ->whereNotNull('effective_to')
                ->where('effective_to', '<', now()->toDateString())
                ->count(),
        ];

        return Inertia::render('water-rate/index', [
            'waterRates' => $this->waterRateService->paginate(
                is_string($search) ? $search : null,
                10
            ),
            'stats' => $stats,
            'filter' => [
                'search' => is_string($search) ? $search : '',
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('water-rate/index', [
            'waterRates' => $this->waterRateService->paginate(10),
            'filter' => [
                'search' => '',
            ],
            'rateOptions' => WaterRate::query()
                ->orderBy('effective_from', 'desc')
                ->get(),
        ]);
    }

    public function store(WaterRateRequest $request)
    {
        $this->waterRateService->create($request->validated());

        return back()->with('success', 'Water rate created successfully.');
    }

    public function show(int $id)
    {
        return response()->json(
            $this->waterRateService->findOrFail($id)
        );
    }

    public function update(WaterRateRequest $request, int $id)
    {
        $waterRate = $this->waterRateService->findOrFail($id);

        $this->waterRateService->update(
            $waterRate,
            $request->validated()
        );

        return back()->with('success', 'Water rate updated successfully.');
    }

    public function destroy(int $id)
    {
        $waterRate = $this->waterRateService->findOrFail($id);

        $this->waterRateService->delete($waterRate);

        return back()->with('success', 'Water rate deleted successfully.');
    }
}