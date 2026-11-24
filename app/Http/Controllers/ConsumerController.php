<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsumerRequest;
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

        return Inertia::render(
            'consumer/index',
            $this->consumerService->indexData(is_string($search) ? $search : null)
        );
    }

    public function store(ConsumerRequest $request)
    {
        $this->consumerService->create($request->validated());

        return back()->with('success', 'Consumer created successfully.');
    }

    public function show(int $id)
    {
        return response()->json(
            $this->consumerService->findOrFail($id)
        );
    }

    public function update(ConsumerRequest $request, int $id)
    {
        $consumer = $this->consumerService->findOrFail($id);

        $this->consumerService->update(
            $consumer,
            $request->validated()
        );

        return back()->with('success', 'Consumer updated successfully.');
    }

    public function destroy(int $id)
    {
        $consumer = $this->consumerService->findOrFail($id);

        $this->consumerService->delete($consumer);

        return back()->with('success', 'Consumer deleted successfully.');
    }
}
