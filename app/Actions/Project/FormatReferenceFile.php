<?php

namespace App\Actions\Project;

use Lorisleiva\Actions\Concerns\AsAction;

class FormatReferenceFile
{
    use AsAction;

    public function handle(object $references, int $projectId)
    {
        $group = [];
        $fileDocumentType = ['doc', 'docx', 'xlsx', 'pdf'];

        foreach ($references as $key => $reference) {
            if ($reference->type == 'link') {
                $group['link'][] = [
                    'media_path' => 'link',
                    'link' => $reference->media_path,
                    'id' => $reference->id,
                    'name' => $reference->name,
                ];
            } elseif (in_array($reference->type, $fileDocumentType)) {
                $group['pdf'][] = [
                    'id' => $reference->id,
                    'name' => 'document',
                    'media_path' => asset('storage/projects/references/'.$projectId).'/'.$reference->media_path,
                    'type' => $reference->type,
                ];
            } else {
                $group['files'][] = [
                    'id' => $reference->id,
                    'media_path' => $reference->media_path_text,
                    'name' => $reference->name,
                    'type' => $reference->type,
                ];
            }
        }

        return $group;
    }
}
