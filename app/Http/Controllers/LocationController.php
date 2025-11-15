<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LocationController extends Controller
{
    //
    public function index(Request $request)
    {
        $locations = Location::query()
            ->where('shop_id', $request->user()->shop_id)
            ->orderBy('priority')
            ->get(['id','name','code','is_active','priority']);


        return Inertia::render('Locations/Index', [
            'locations' => $locations,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'nullable|string|max:60',
            'priority' => 'nullable|integer|min:1',
        ]);


        $loc = Location::create([
            'shop_id' => $request->user()->shop_id,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'priority' => $data['priority'] ?? 100,
        ]);


        return response()->json(['location' => $loc], 201);
    }
}
