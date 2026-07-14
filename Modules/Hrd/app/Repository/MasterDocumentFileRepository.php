<?php

namespace Modules\Hrd\Repository;

use App\Enums\Hrd\Signature\Template\DocumentFileStatus;
use App\Repository\BaseRepository;
use Modules\Hrd\Models\MasterDocument;
use Modules\Hrd\Models\MasterDocumentFile;

class MasterDocumentFileRepository extends BaseRepository
{
    public function __construct(MasterDocumentFile $model)
    {
        return parent::__construct($model);
    }

    public function approveDocument(array $payload, MasterDocumentFile $model): void
    {
        // Make current active version to inactive
        if ($payload['status'] == DocumentFileStatus::Active) {
            MasterDocumentFile::active()
                ->makeArchived();

            // Update text version in master document
            MasterDocument::where('id', $model->master_document_id)
                ->update([
                    'current_active_version_text' => 'v' . $model->version
                ]);
        }

        // Update given document data
        $this->update($model, $payload);
    }
}
