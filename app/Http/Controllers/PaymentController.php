<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsumerPaymentRequest;
use App\Http\Requests\PaymentRequest;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    public function index(Request $req): Response
    {
        $search = $req->input('search');

        return Inertia::render(
            'payment/index',
            $this->paymentService->indexData(is_string($search) ? $search : null)
        );
    }

    public function store(PaymentRequest $request)
    {
        $this->paymentService->create($request->validated());

        return back()->with('success', 'Payment recorded successfully.');
    }

    public function show(int $id)
    {
        return response()->json(
            $this->paymentService->findOrFail($id)
        );
    }

    public function update(PaymentRequest $request, int $id)
    {
        $payment = $this->paymentService->findOrFail($id);

        $this->paymentService->update($payment, $request->validated());

        return back()->with('success', 'Payment updated successfully.');
    }

    public function destroy(int $id)
    {
        $this->paymentService->delete($this->paymentService->findOrFail($id));

        return back()->with('success', 'Payment deleted successfully.');
    }

    public function payForConsumer(int $billId, ConsumerPaymentRequest $request): RedirectResponse
    {
        $this->paymentService->payForConsumer($request->user(), $billId, $request->validated());

        return to_route('dashboard')->with('success', 'Payment recorded successfully.');
    }
}
