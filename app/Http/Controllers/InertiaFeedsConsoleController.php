<?php

namespace App\Http\Controllers;

use App\Models\FeedBatch;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InertiaFeedsConsoleController extends Controller
{
    //
    public function index() {
        $batches = FeedBatch::with('channel')->orderByDesc('id')->paginate(20);
        return Inertia::render('Feeds/Index', ['batches' => $batches]);
    }

    public function show(FeedBatch $batch) {
        $batch->load(['channel','items']);
        return Inertia::render('Feeds/Show', ['batch' => $batch]);
    }
}
