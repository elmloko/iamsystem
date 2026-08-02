<?php

namespace App\Http\Controllers;

use App\Services\SystemAccountProvisioner;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemUserSearchController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Users/Search');
    }

    public function search(Request $request, SystemAccountProvisioner $provisioner)
    {
        $query = $request->string('q')->trim()->value();

        if (strlen($query) < 2) {
            return response()->json(['groups' => []]);
        }

        return response()->json([
            'groups' => $provisioner->searchByName($query),
        ]);
    }
}
