<?php

namespace Modules\Email\Data\Notification;

use Modules\Email\Data\BaseData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SlackTableHeaderColumnData extends BaseData
{
    /**
     * @param  DataCollection<int, SlackTableHeaderSectionData>  $elements
     */
    public function __construct(
        public string $type,
        #[DataCollectionOf(SlackTableHeaderSectionData::class)]
        public DataCollection $elements,
    ) {}
}
