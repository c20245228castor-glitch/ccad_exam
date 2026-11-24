<?php

namespace App\Http\Controllers;

use App\Http\Requests\WaterMeterRequest;
use App\Services\WaterMeterService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaterMeterController extends Controller
{
    public function __construct(
        private readonly WaterMeterService $waterMeterService
    ) {}

    public function index(Request $req): Response
    {
        $search = $req->input('search');

        return Inertia::render(
            'water-meter/index',
            $this->waterMeterService->indexData(is_string($search) ? $search : null)
        );
    }

    public function store(WaterMeterRequest $request)
    {
        $this->waterMeterService->create($request->validated());

        return back()->with('success', 'Water meter created successfully.');
    }

    public function show(int $id)
    {
        return response()->json(
            $this->waterMeterService->findOrFail($id)
        );
    }

    public function update(WaterMeterRequest $request, int $id)
    {
        $waterMeter = $this->waterMeterService->findOrFail($id);

        $this->waterMeterService->update(
            $waterMeter,
            $request->validated()
        );

        return back()->with('success', 'Water meter updated successfully.');
    }

    public function destroy(int $id)
    {
        $waterMeter = $this->waterMeterService->findOrFail($id);

        $this->waterMeterService->delete($waterMeter);

        return back()->with('success', 'Water meter deleted successfully.');
    }
}
