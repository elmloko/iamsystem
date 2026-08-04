<?php

namespace App\Http\Controllers;

use App\Models\SystemEntry;
use Inertia\Inertia;
use Inertia\Response;

class SystemController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Systems/Index', [
            'systems' => SystemEntry::orderBy('name')->get([
                'id', 'key', 'name', 'status', 'connection', 'notes',
            ]),
        ]);
    }
}
