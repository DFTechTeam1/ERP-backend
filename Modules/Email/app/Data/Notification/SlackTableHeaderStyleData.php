<?php

namespace Modules\Email\Data\Notification;

use Modules\Email\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SlackTableHeaderStyleData extends BaseData
{
    public function __construct(
        public bool $bold,
    ) {}
}
