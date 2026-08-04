<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\SystemAccount;
use App\Models\SystemEntry;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $systems = SystemEntry::orderBy('name')->get(['id', 'key', 'name', 'status']);

        return Inertia::render('Dashboard', [
            'stats' => [
                'systemsActive' => $systems->where('status', 'active')->count(),
                'systemsPending' => $systems->where('status', 'pending')->count(),
                'people' => Person::count(),
                'accounts' => SystemAccount::count(),
            ],
            'systems' => $systems,
        ]);
    }
}
