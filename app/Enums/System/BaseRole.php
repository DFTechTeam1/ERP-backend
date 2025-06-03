<?php

namespace App\Enums\System;

enum BaseRole: string
{
    case ProjectManager = 'project manager';
    case Root = 'root';
    case Marketing = 'marketing';
    case Director = 'director';
    case Production = 'production';
    case Entertainment = 'entertainment';
    case ProjectManagerAdmin = 'project manager admin';
    case ItSupport = 'it support';
    case Hrd = 'hrd';
    case Finance = 'finance';
    case RegularEmployee = 'regulare employee';
    case AssistantProjectManger = 'assistant manager';
    case ProjectManagerEntertainment = 'project manager entertainment';
    case LeadModeller = 'lead modeller';
}
