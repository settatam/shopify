<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\FeedBatch;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FeedsConsoleController extends Controller
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
