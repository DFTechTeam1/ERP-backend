<?php

namespace Modules\Inventory\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DeleteImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Define inventory image name
     *
     * @var array
     */
    public array $images;

    /**
     * Define inventory image folder
     *
     * @var string
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
            deleteImage(public_path('storage/' . $this->imageFolder . '/' . $image));
        }
    }
}
