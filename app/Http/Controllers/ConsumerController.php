<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsumerRequest;
use App\Models\Consumer;
use App\Services\ConsumerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsumerController extends Controller
{
    public function __construct(
        private readonly ConsumerService $consumerService
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('consumer/index', [
            'consumers' => $this->consumerService->paginate($search ?: null),
            'stats' => [
                'total' => Consumer::query()->count(),
                'with_meters' => Consumer::query()->has('waterMeters')->count(),
                'without_meters' => Consumer::query()->doesntHave('waterMeters')->count(),
            ],
            'filter' => ['search' => $search],
        ]);
    }

    public function store(ConsumerRequest $request): RedirectResponse
    {
        $this->consumerService->create($request->validated());

        return back()->with('success', 'Consumer created successfully.');
    }

    public function show(Consumer $consumer)
    {
        return response()->json(
            $consumer->load('waterMeters.waterBills')
        );
    }

    public function update(ConsumerRequest $request, Consumer $consumer): RedirectResponse
    {
        $this->consumerService->update($consumer, $request->validated());

        return back()->with('success', 'Consumer updated successfully.');
    }

    public function destroy(Consumer $consumer): RedirectResponse
    {
        $this->consumerService->delete($consumer);

        return back()->with('success', 'Consumer deleted successfully.');
    }
}
