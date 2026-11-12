<?php

namespace App\Http\Controllers;

use App\Http\Requests\WaterBillRequest;
use App\Models\WaterBill;
use App\Models\WaterMeter;
use App\Models\WaterRate;
use App\Services\WaterBillService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaterBillController extends Controller
{
    public function __construct(
        private readonly WaterBillService $waterBillService
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $today = today()->toDateString();

        return Inertia::render('water-bill/index', [
            'bills' => $this->waterBillService->paginate($search ?: null),
            'stats' => [
                'total' => WaterBill::query()->count(),
                'paid' => WaterBill::query()->where('status', 'paid')->count(),
                'unpaid' => WaterBill::query()->where('status', 'unpaid')->count(),
                'overdue' => WaterBill::query()
                    ->where('status', 'unpaid')
                    ->whereDate('due_date', '<', $today)
                    ->count(),
            ],
            'meters' => WaterMeter::query()
                ->with('consumer')
                ->where('is_active', true)
                ->orderBy('meter_serial')
                ->get(),
            'rates' => WaterRate::query()
                ->orderByDesc('effective_from')
                ->get(),
            'filter' => ['search' => $search],
        ]);
    }

    public function store(WaterBillRequest $request): RedirectResponse
    {
        $this->waterBillService->create($request->validated());

        return back()->with('success', 'Water bill created successfully.');
    }

    public function show(WaterBill $waterBill)
    {
        return response()->json(
            $waterBill->load([
                'waterMeter.consumer',
                'waterRate',
                'payments',
            ])
        );
    }

    public function update(WaterBillRequest $request, WaterBill $waterBill): RedirectResponse
    {
        $this->waterBillService->update($waterBill, $request->validated());

        return back()->with('success', 'Water bill updated successfully.');
    }

    public function destroy(WaterBill $waterBill): RedirectResponse
    {
        $this->waterBillService->delete($waterBill);

        return back()->with('success', 'Water bill deleted successfully.');
    }
}
