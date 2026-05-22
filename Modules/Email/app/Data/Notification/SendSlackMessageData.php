<?php

namespace Modules\Email\Data\Notification;

use Modules\Email\Data\BaseData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\DataCollection;

final class SendSlackMessageData extends BaseData
{
    /**
     * @param  DataCollection<int, SlackSectionBlockData>  $sectionBlock
     * @param  DataCollection<int, SlackSectionBlockData>  $contextBlock
     */
    public function __construct(
        public string $messageTitle,
        public string $title,
        #[DataCollectionOf(SlackSectionBlockData::class)]
        public ?DataCollection $sectionBlock,
        #[DataCollectionOf(SlackSectionBlockData::class)]
        public ?DataCollection $contextBlock,
    ) {}
}
