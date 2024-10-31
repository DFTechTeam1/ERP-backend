<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\ApproveRequestTeamMemberJob;
use Modules\Production\Models\TransferTeamMember;
use Tests\TestCase;

class ApproveRequestTeamMemberJobTest extends TestCase
{
    use RefreshDatabase, WithFaker;
    /**
     * A basic feature test example.
     */
    public function test_send_job(): void
    {
        Queue::fake();

        $transfers = TransferTeamMember::select('id')
            ->limit(3)
            ->get();

        ApproveRequestTeamMemberJob::dispatch($transfers->pluck('id')->toArray());

        Queue::assertPushed(ApproveRequestTeamMemberJob::class);
    }
}
