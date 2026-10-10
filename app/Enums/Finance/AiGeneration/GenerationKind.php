<?php

namespace App\Enums\Finance\AiGeneration;

enum GenerationKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Element = 'element';
    case Storyboard = 'storyboard';
    case Keyframe = 'keyframe';
    case Motion = 'motion';
    case Chat = 'chat';
    case Enhancer = 'enhancer';
    case ImageEdit = 'image_edit';
}
