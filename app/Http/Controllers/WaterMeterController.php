<?php
namespace App\Http\Controllers;

use App\Http\Requests\WaterMeterRequest;
use App\Models\WaterMeter;
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

        $stats = [
            'total' => WaterMeter::query()->count(),
            'active' => WaterMeter::query()->where('is_active', true)->count(),
            'inactive' => WaterMeter::query()->where('is_active', false)->count(),
            'assigned' => WaterMeter::query()->whereNotNull('consumer_id')->count(),
        ];

        return Inertia::render('water-meter/index', [
            'waterMeters' => $this->waterMeterService->paginate(
                is_string($search) ? $search : null,
                10
            ),
            'stats' => $stats,
            'filter' => [
                'search' => is_string($search) ? $search : '',
            ],
        ]);
    }

    public function store(WaterMeterRequest $request)
    {
        $this->waterMeterService->create($request->validated());

        return back()->with('success', 'Water meter created successfully.');
    }

    public function show(int $id)
    {
        return response()->json(
            WaterMeter::with(['consumer', 'waterBills'])->findOrFail($id)
        );
    }

    public function update(WaterMeterRequest $request, int $id)
    {
        $waterMeter = WaterMeter::findOrFail($id);

        $this->waterMeterService->update(
            $waterMeter,
            $request->validated()
        );

        return back()->with('success', 'Water meter updated successfully.');
    }

    public function destroy(int $id)
    {
        $waterMeter = WaterMeter::findOrFail($id);

        $this->waterMeterService->delete($waterMeter);

        return back()->with('success', 'Water meter deleted successfully.');
    }
}