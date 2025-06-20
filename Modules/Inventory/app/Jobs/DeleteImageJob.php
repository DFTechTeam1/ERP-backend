<?php

namespace Modules\Inventory\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Define inventory image name
     */
    public array $images;

    /**
     * Define inventory image folder
     */
    private string $imageFolder = 'inventory';

    /**
     * Create a new job instance.
     */
    public function __construct(array $images)
    {
        $this->images = $images;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->images as $image) {
            deleteImage(public_path('storage/'.$this->imageFolder.'/'.$image));
        }
    }
}
