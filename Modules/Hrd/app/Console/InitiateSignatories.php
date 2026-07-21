<?php

namespace Modules\Hrd\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Company\Repository\DivisionRepository;
use Modules\Hrd\Models\SignatoriesMapping;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class InitiateSignatories extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hrd:initiate-signatories';

    /**
     * The console command description.
     */
    protected $description = 'Initiate signatories mapping default without assign any signer yet';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $payload = app(DivisionRepository::class)
            ->list(
                select: 'uid,id,name',
            )->map(function ($division) {
                return [
                    'division_id' => $division->id,
                    'main_signer_id' => null,
                    'delegate_signer_id' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'uid' => Uuid::uuid4()->toString()
                ];
            })->toArray();

        $current = SignatoriesMapping::select('id')
            ->latest()
            ->first();

        if (! $current) {
            SignatoriesMapping::insert($payload);
            $this->info('Success to generate signatories mapping');
        }

        $this->info('Signatories data already exists');
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['example', InputArgument::REQUIRED, 'An example argument.'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
        ];
    }
}
