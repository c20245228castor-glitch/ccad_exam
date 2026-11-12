<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\Payment;
use App\Models\WaterBill;
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

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('payment/index', [
            'payments' => $this->paymentService->paginate($search ?: null),
            'stats' => [
                'total' => Payment::query()->count(),
                'amount_paid' => Payment::query()->sum('amount_paid'),
                'unique_bills' => Payment::query()->distinct()->count('bill_id'),
                'recent' => Payment::query()
                    ->whereDate('payment_date', today())
                    ->count(),
            ],
            'unpaidBills' => WaterBill::query()
                ->with(['waterMeter.consumer', 'waterRate'])
                ->where('status', 'unpaid')
                ->orderBy('due_date')
                ->get(),
            'filter' => ['search' => $search],
        ]);
    }

    public function store(PaymentRequest $request): RedirectResponse
    {
        $this->paymentService->create($request->validated());

        return back()->with('success', 'Payment recorded successfully.');
    }

    public function show(Payment $payment)
    {
        return response()->json(
            $this->paymentService->getById($payment)
        );
    }
}
