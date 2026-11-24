<?php

namespace App\Http\Controllers;

use App\Http\Requests\WaterRateRequest;
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

        return Inertia::render(
            'water-rate/index',
            $this->waterRateService->indexData(is_string($search) ? $search : null)
        );
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
