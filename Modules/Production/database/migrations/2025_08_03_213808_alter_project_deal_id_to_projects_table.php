<?php

use App\Enums\Production\ProjectDealStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectDeal;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_deal_id')
                ->nullable()
                ->constrained(table: 'project_deals', column: 'id')
                ->onUpdate('set null');
        });

        // connecting projects
        $finalProjects = ProjectDeal::selectRaw('id,name,project_date')
            ->where('status', ProjectDealStatus::Final)
            ->get();

        foreach ($finalProjects as $finalProject) {
            $project = Project::selectRaw('id,name,project_date')
                ->whereNull('project_deal_id')
                ->where('name', $finalProject->name)
                ->where('project_date', $finalProject->project_date)
                ->first();

            if ($project) {
                Project::where('id', $project->id)
                    ->update([
                        'project_deal_id' => $finalProject->id,
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Project::whereNotNull('project_deal_id')
            ->update([
                'project_deal_id' => null,
            ]);

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['project_deal_id']);

            $table->dropColumn('project_deal_id');
        });
    }
};