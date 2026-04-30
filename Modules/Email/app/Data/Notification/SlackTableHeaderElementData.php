<?php

namespace Modules\Email\Data\Notification;

use Modules\Email\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SlackTableHeaderElementData extends BaseData
{
    public function __construct(
        public string $type,
        public string $text,
        public SlackTableHeaderStyleData $style,
    ) {}
}
