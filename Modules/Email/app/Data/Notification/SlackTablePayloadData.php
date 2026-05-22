<?php

namespace Modules\Email\Data\Notification;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SlackTablePayloadData extends Data {
    public function __construct(
        #[DataCollectionOf(SlackTableHeaderColumnData::class)]
        public DataCollection $payload
    ) {}
}