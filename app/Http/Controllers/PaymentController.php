<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\Payment;
use App\Services\PaymentService;
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

        $stats = [
            'total' => Payment::query()->count(),
            'amount_paid' => Payment::query()->sum('amount_paid'),
            'unique_bills' => Payment::query()->distinct('bill_id')->count('bill_id'),
            'recent' => Payment::query()->whereDate('payment_date', now()->toDateString())->count(),
        ];

        return Inertia::render('payment/index', [
            'payments' => $this->paymentService->paginate(
                is_string($search) ? $search : null,
                10
            ),
            'stats' => $stats,
            'filter' => [
                'search' => is_string($search) ? $search : '',
            ],
        ]);
    }

    public function store(PaymentRequest $request)
    {
        $this->paymentService->create($request->validated());

        return back()->with('success', 'Payment recorded successfully.');
    }

    public function show(int $id)
    {
        return response()->json(
            Payment::with('waterBill')->findOrFail($id)
        );
    }
}