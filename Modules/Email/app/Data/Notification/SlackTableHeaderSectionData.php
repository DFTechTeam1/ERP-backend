<?php

namespace Modules\Email\Data\Notification;

use Modules\Email\Data\BaseData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SlackTableHeaderSectionData extends BaseData
{
    /**
     * @param  DataCollection<int, SlackTableHeaderElementData>  $elements
     */
    public function __construct(
        public string $type,
        #[DataCollectionOf(SlackTableHeaderElementData::class)]
        public DataCollection $elements,
    ) {}
}
