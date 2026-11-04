<?php
namespace App\Jobs;

use App\Models\Investigation;
use App\Services\DiagnosticEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessInvestigation implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $investigationId) {}

    public function handle(DiagnosticEngine $engine): void
    {
        $investigation = Investigation::with('evidence')->findOrFail($this->investigationId);
        $investigation->update(['status' => 'analyzing']);
        $engine->run($investigation);
        $investigation->events()->create(['type' => 'processed', 'message' => 'Diagnostics completed']);
    }
}
