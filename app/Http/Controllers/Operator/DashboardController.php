<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Services\UtilizationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, UtilizationService $utilization): Response
    {
        $tenant = $request->user()->tenant;

        return Inertia::render('operator/Dashboard', [
            'metrics' => $utilization->dashboardMetrics($tenant),
            'currency' => $tenant->currency,
        ]);
    }
}
