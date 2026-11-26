<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\ConsumerDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return Inertia::render(
                'consumer/dashboard',
                app(ConsumerDashboardService::class)->data($user)
            );
        }

        return Inertia::render('dashboard', $this->dashboardService->data());
    }
}
