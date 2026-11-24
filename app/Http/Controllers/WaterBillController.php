<?php

namespace App\Http\Controllers;

use App\Http\Requests\WaterBillRequest;
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

        return Inertia::render(
            'water-bill/index',
            $this->waterBillService->indexData(is_string($search) ? $search : null)
        );
    }

    public function store(WaterBillRequest $request)
    {
        $this->waterBillService->create($request->validated());

        return back()->with('success', 'Water bill created successfully.');
    }

    public function show(int $id)
    {
        return response()->json(
            $this->waterBillService->findOrFail($id)
        );
    }

    public function update(WaterBillRequest $request, int $id)
    {
        $waterBill = $this->waterBillService->findOrFail($id);

        $this->waterBillService->update(
            $waterBill,
            $request->validated()
        );

        return back()->with('success', 'Water bill updated successfully.');
    }

    public function destroy(int $id)
    {
        $waterBill = $this->waterBillService->findOrFail($id);

        $this->waterBillService->delete($waterBill);

        return back()->with('success', 'Water bill deleted successfully.');
    }
}
