<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Facades\Module;

class RepositoryGenerator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-repository
                            {name : Repository name without Repository Suffix}
                            {module : Active Module name}
                            {--model= : Model name. If not registered, new model will be create}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to create repository';

    private $namespace;

    private $moduleName;

    private $configModulePath;

    private $repositoryDir;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $phpExt = '.php';
        $stubExt = '.stub';

        $name = $this->argument('name');
        $module = $this->argument('module');

        $model = $this->option('model');

        // validate
        $moduleData = Module::find($module);
        if (! $moduleData) {
            $this->error('Module not found');

            $this->info('Operation stopped');
            exit();
        }

        $this->moduleName = $module;

        $this->namespace = config('modules.namespace');

        $this->configModulePath = config('modules.paths.modules');

        $this->repositoryDir = $this->configModulePath.'/'.$this->moduleName.'/app/Repository/';

        if (file_exists($this->repositoryDir."{$name}Repository.php")) {
            $this->error('Repository already exists');
            $this->error('Operation stopped');
            exit();
        }

        $modulePath = $moduleData->getPath();

        $modelPath = $modulePath.'/app/Models';

        if (! file_exists("{$modelPath}/$model{$phpExt}")) {
            Artisan::call('module:make-model '.$model.' '.$module);
        }

        $this->buildInterface($name);
        $this->buildRepository($name, $model);
    }

    /**
     * Create Interface
     *
     * @return void
     */
    private function buildInterface(string $name)
    {
        $dir = $this->configModulePath.'/'.$this->moduleName.'/app/Repository/Interface/';

        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0777, true);
        }

        $namespace = $this->namespace.'\\'.$this->moduleName.'\\Repository\\Interface';

        $className = "{$name}Interface";

        $stub = file_get_contents(__DIR__.'/../../../stubs/repository.interface.stub');

        $filename = "{$className}.php";

        $targetPath = $dir.$filename;

        $content = str_replace(
            ['{{namespace}}', '{{className}}'],
            [$namespace, $className],
            $stub
        );

        file_put_contents($targetPath, $content);

        $this->info('Repository Interface created');
    }

    /**
     * Create Repository
     *
     *
     * @return void
     */
    private function buildRepository(string $name, string $model)
    {
        if (! is_dir($this->repositoryDir)) {
            File::makeDirectory($this->repositoryDir, 0777, true);
        }

        $namespace = $this->namespace.'\\'.$this->moduleName.'\\Repository';

        $className = "{$name}Repository";

        $filename = "{$className}.php";

        $modelNamespace = $this->namespace.'\\'.$this->moduleName."\\Models\\{$model}";

        $interfaceNamespace = $this->namespace.'\\'.$this->moduleName."\\Repository\\Interface\\{$name}Interface";

        $interface = "{$name}Interface";

        $stub = file_get_contents(__DIR__.'/../../../stubs/repository.stub');

        $content = str_replace(
            ['{{namespace}}', '{{className}}', '{{modelNamespace}}', '{{modelName}}', '{{interfaceNamespace}}', '{{interface}}'],
            [$namespace, $className, $modelNamespace, $model, $interfaceNamespace, $interface],
            $stub
        );

        $targetPath = $this->repositoryDir.$filename;

        file_put_contents($targetPath, $content);

        $this->info('Repository created');
    }
}
