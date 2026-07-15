<?php

namespace Modules\Hrd\Repository;

use App\Data\Hrd\Signature\SelectedOrgSignatureSignerData;
use App\Data\Hrd\Signature\SignatoriesDivisionPicData;
use App\Repository\BaseRepository;
use Modules\Company\Models\DivisionBackup;
use Modules\Hrd\Models\SignatoriesMapping;

class SignatoriesMappingRepository extends BaseRepository
{
    public function __construct(SignatoriesMapping $model)
    {
        return parent::__construct($model);
    }

    /**
     * Get division signatories mapping
     *
     * @return array<int, SignatoriesDivisionPicData>
     */
    public function getMappingWithHeadCount(): array
    {
        $globalDivisionSignatories = getSettingByKey('global_signatory_divisions');

        $model = $this->query();
        $model->select(['id', 'uid', 'division_id', 'main_signer_id', 'delegate_signer_id'])
            ->with([
                'division:id,uid,name',
                'division.positions:id,division_id',
                'division.positions.employees' => function ($query) {
                    $query->selectRaw('id,uid,name,position_id,avatar_color')
                        ->activeEmployee();
                },
                'mainSigner:id,name,position_id,avatar_color',
                'delegateSigner:id,uid,name,position_id,avatar_color',
            ]);

        // Exclude the global signatories division
        if ($globalDivisionSignatories) {
            $globalDivisionIds = collect(json_decode($globalDivisionSignatories, true))
                ->map(function ($item) {
                    return getIdFromUid($item, new DivisionBackup());
                })->toArray();

            $model->whereNotIn('division_id', $globalDivisionIds);
        }

        /** @var array<int, SignatoriesDivisionPicData> */
        $output = [];

        foreach ($model->get() as $data) {
            $count = 0;
            
            /** @var array<int, SelectedOrgSignatureSignerData> */
            $signerDivisions = [];

            foreach ($data->division->positions as $position) {
                $count += $position->employees->count();

                // Define signers in each divisions
                foreach ($position->employees as $employee) {
                    $signerDivisions[] = new SelectedOrgSignatureSignerData(
                        uid: $employee->uid,
                        name: $employee->name,
                        role: $employee->position->name,
                        initial: getInitialName($employee->name),
                        color: $employee?->avatar_color ?? null
                    );
                }
            }

            $mainSigner = null;
            if ($data->mainSigner) {
                $mainSigner = new SelectedOrgSignatureSignerData(
                    uid: $data->mainSigner->uid,
                    name: $data->mainSigner->name,
                    role: $data->mainSigner->position->name,
                    initial: getInitialName($data->mainSigner->name),
                    color: $data->mainSigner?->avatar_color ?? null
                );
            }

            $delegateSigner = null;
            if ($data->delegateSigner) {
                $delegateSigner = new SelectedOrgSignatureSignerData(
                    uid: $data->delegateSigner->uid,
                    name: $data->delegateSigner->name,
                    role: $data->delegateSigner->position->name,
                    initial: getInitialName($data->delegateSigner->name),
                    color: $data->delegateSigner?->avatar_color ?? null
                );
            }

            $output[] = new SignatoriesDivisionPicData(
                uid: $data->uid,
                division_name: $data->division->name,
                division_code: '',
                headcount: $count,
                pic: $mainSigner,
                delegate: $delegateSigner,
                signer_options: $signerDivisions
            );
        }

        return $output;
    }
}
