<?php

namespace   App\Http\Controllers;

use App\Http\Requests\ConsumerRequest;
use App\Models\Consumer;
use App\Models\WaterMeter;
use App\Services\ConsumerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsumerController extends Controller
{
    public function __construct(
        private readonly ConsumerService $consumerService
    ) {}

    public function index(Request $req): Response
    {
        $search = $req->input('search');

        $stats = [
            'total' => Consumer::query()->count(),
            'with_meters' => Consumer::query()->has('waterMeters')->count(),
            'without_meters' => Consumer::query()->doesntHave('waterMeters')->count(),
            'active_meters' => WaterMeter::query()->where('is_active', true)->count(),
        ];

        return Inertia::render('consumer/index', [
            'consumers' => $this->consumerService->paginate(
                is_string($search) ? $search : null,
                10
            ),
            'stats' => $stats,
            'filter' => [
                'search' => is_string($search) ? $search : '',
            ],
        ]);
    }

    public function store(ConsumerRequest $request)
    {
        $this->consumerService->create($request->validated());

        return back()->with('success', 'Consumer created successfully.');
    }

    public function show(int $id)
    {
        return response()->json(
            \App\Models\Consumer::with('waterMeters')->findOrFail($id)
        );
    }

    public function update(ConsumerRequest $request, int $id)
    {
        $consumer = \App\Models\Consumer::findOrFail($id);

        $this->consumerService->update(
            $consumer,
            $request->validated()
        );

        return back()->with('success', 'Consumer updated successfully.');
    }

    public function destroy(int $id)
    {
        $consumer = \App\Models\Consumer::findOrFail($id);

        $this->consumerService->delete($consumer);

        return back()->with('success', 'Consumer deleted successfully.');
    }
}