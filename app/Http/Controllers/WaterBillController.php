<?php

namespace App\Http\Controllers;

use App\Http\Requests\WaterBillRequest;
use App\Models\WaterBill;
use App\Services\WaterBillService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaterBillController extends Controller
{
    public function __construct(
        private readonly WaterBillService $waterBillService
    ) {}

    public function index(Request $req): Response
    {
        $search = $req->input('search');

        $stats = [
            'total' => WaterBill::query()->count(),
            'paid' => WaterBill::query()->where('status', 'paid')->count(),
            'unpaid' => WaterBill::query()->where('status', 'unpaid')->count(),
            'overdue' => WaterBill::query()
                ->where('status', 'unpaid')
                ->whereDate('due_date', '<', now()->toDateString())
                ->count(),
        ];

        return Inertia::render('water-bill/index', [
            'bills' => $this->waterBillService->paginate(
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
        return Inertia::render('water-bill/index', [
            'waterBills' => $this->waterBillService->paginate(10),
            'filter' => [
                'search' => '',
            ],
            'meters' => \App\Models\WaterMeter::query()
                ->with('consumer')
                ->orderBy('meter_serial')
                ->get(),
            'rates' => \App\Models\WaterRate::query()
                ->orderBy('effective_from', 'desc')
                ->get(),
        ]);
    }

    public function store(WaterBillRequest $request)
    {
        $this->waterBillService->create($request->validated());

        return back()->with('success', 'Water bill created successfully.');
    }

    public function show(int $id)
    {
        return response()->json(
            WaterBill::with([
                'waterMeter.consumer',
                'waterRate',
                'payments',
            ])->findOrFail($id)
        );
    }

    public function update(WaterBillRequest $request, int $id)
    {
        $waterBill = WaterBill::findOrFail($id);

        $this->waterBillService->update(
            $waterBill,
            $request->validated()
        );

        return back()->with('success', 'Water bill updated successfully.');
    }

    public function destroy(int $id)
    {
        $waterBill = WaterBill::findOrFail($id);

        $this->waterBillService->delete($waterBill);

        return back()->with('success', 'Water bill deleted successfully.');
    }
}