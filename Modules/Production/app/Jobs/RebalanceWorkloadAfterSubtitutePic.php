<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RebalanceWorkloadAfterSubtitutePic implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly int $projectId,
        private readonly string $leadUid
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Advisory re-balance against the Python service. Skip when it is not configured, cap the
        // request so a slow/unreachable service can't stall the PIC update (this runs inside the
        // assignPic/subtitutePic transaction), and never let a failure bubble up or roll back.
        $endpoint = config('app.python_endpoint');
        if (! $endpoint) {
            return;
        }

        try {
            Http::connectTimeout(3)
                ->withToken(request()->bearerToken())
                ->timeout(20)
                ->get($endpoint . '/pic-assignment/v2/suggest-pic/' . $this->leadUid . '?max_window=4');
        } catch (\Throwable $th) {
            Log::warning('Project lead PIC rebalance failed', [
                'project_id' => $this->projectId,
                'lead_uid' => $this->leadUid,
                'error' => $th->getMessage(),
            ]);
        }
    }
}
