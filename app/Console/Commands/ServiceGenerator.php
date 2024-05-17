<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Facades\Module;

use function Termwind\ask;

class ServiceGenerator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-service
                            {name : Service Name}
                            {module : Active module name}
                            {--repo= : Repository name without Repository suffix. If empty, system will automatically create a new one base on serveice name}
                            {--model= : Model name. If empty, system will create base on service name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Service';

    private $moduleName;

    private $namespace;

    private $configModulePath;

    private $repositoryDir;

    private $serviceDir;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $phpExt = '.php';
            $name = $this->argument('name');
            $module = $this->argument('module');
    
            $repository = $this->option('repo');
            $repository = empty($repository) ? $name : $repository;
    
            $model = $this->option('model');
            $model = empty($model) ? $name : $model; 
    
            // validate
            $moduleData = Module::find($module);
            if (!$moduleData) {
                $this->error('Module not found');
    
                $this->info('Operation stopped');
                exit();
            }
    
            $this->moduleName = $module;
    
            $this->namespace = config('modules.namespace');
    
            $this->configModulePath = config('modules.paths.modules');
    
            $this->repositoryDir = $this->configModulePath . "/" . $this->moduleName . "/app/Repository/";
    
            $this->serviceDir = $this->configModulePath . "/" . $this->moduleName . "/app/Services/";
    
            if (file_exists($this->serviceDir . "{$name}Service.php")) {
                $this->error('Service already exists');
                $this->error('Operation stopped');
                exit();
            }
    
            $modulePath = $moduleData->getPath();
            
            $modelPath = $modulePath . '/app/Models';
    
            if (!file_exists("{$modelPath}/$model{$phpExt}")) {
                Artisan::call('module:make-model ' . $model . ' ' . $module);
            }
    
            // validate repository
            if (!file_exists($this->repositoryDir . "{$repository}Repository.php")) {
                Artisan::call("app:make-repository {$name} {$module} --model={$name}");
            }
    
            $this->buildService($name, $repository);
        } catch (\Throwable $th) {
            $this->error(errorMessage($th));
        }
    }

    private function buildService(string $name, string $repository)
    {
        try {
            $dir = $this->configModulePath . "/" . $this->moduleName . "/app/Services/";
    
            if (!is_dir($dir)) {
                File::makeDirectory($dir, 0777, true);
            }
    
            $namespace = $this->namespace . "\\" . $this->moduleName . "\\Services";
    
            $namespaceRepository = $this->namespace . "\\" . $this->moduleName . "\\Repository\\{$repository}Repository";
    
            $className = "{$name}Service";
    
            $stub = file_get_contents(__DIR__ . '/../../../stubs/service.stub');
    
            $filename = "{$className}.php";
    
            $targetPath = $dir . $filename;
    
            $content = str_replace(
                ["{{namespace}}", "{{className}}", "{{namespaceRepository}}", "{{repository}}"],
                [$namespace, $className, $namespaceRepository, "{$repository}Repository"],
                $stub
            );
    
            file_put_contents($targetPath, $content);
    
            $this->info('Service created');
        } catch (\Throwable $th) {
            $this->error(errorMessage($th));
        }
    }
}
