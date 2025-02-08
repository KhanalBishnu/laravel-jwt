<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RepoMake extends Command
{
    protected $signature = 'repo-make';
   
    protected $description = 'Create Model, Migration, Controller, Interface, and Repository with bindings';

    public function handle()
    {
        // $model = $this->argument('model');
        $model = $this->ask('Enter the model name (e.g., Product)');

        $modelClass = Str::studly($model); // Capitalize first letter
        $modelPath = app_path("Models/{$modelClass}.php");
        $interfacePath = app_path("Repositories/{$modelClass}RepositoryInterface.php");
        $repositoryPath = app_path("Repositories/{$modelClass}Repository.php");
        $controllerPath = app_path("Http/Controllers/{$modelClass}Controller.php");

        // 1️⃣ Create Model & Migration
        if (!File::exists($modelPath)) {
            $this->call('make:model', ['name' => "{$modelClass}", '--migration' => true]);
            $this->info("✅ Model & Migration created for {$modelClass}");
        } else {
            $this->warn("⚠️ Model already exists: {$modelClass}");
        }

        // 2️⃣ Create Interface
        if (!File::exists($interfacePath)) {
            File::ensureDirectoryExists(dirname($interfacePath));
            File::put($interfacePath, $this->getInterfaceTemplate($modelClass));
            $this->info("✅ Interface created: {$interfacePath}");
        } else {
            $this->warn("⚠️ Interface already exists: {$modelClass}RepositoryInterface");
        }

        // 3️⃣ Create Repository
        if (!File::exists($repositoryPath)) {
            File::ensureDirectoryExists(dirname($repositoryPath));
            File::put($repositoryPath, $this->getRepositoryTemplate($modelClass));
            $this->info("✅ Repository created: {$repositoryPath}");
        } else {
            $this->warn("⚠️ Repository already exists: {$modelClass}Repository");
        }

        // 4️⃣ Create Controller & Inject Repository
        if (!File::exists($controllerPath)) {
            File::put($controllerPath, $this->getControllerTemplate($modelClass));
            $this->info("✅ Controller created: {$controllerPath}");
        } else {
            $this->warn("⚠️ Controller already exists: {$modelClass}Controller");
        }

        $newRepoInterfaceName = "{$modelClass}RepositoryInterface";
        $newRepoName = "{$modelClass}Repository";

        $this->updateAppServiceProvider($newRepoInterfaceName,$newRepoName);

          
    }

    // 🏗️ Interface Template
    protected function getInterfaceTemplate($modelClass)
    {
        return <<<PHP
        <?php

        namespace App\Repositories;

        interface {$modelClass}RepositoryInterface
        {
            public function getAll();
            public function findById(\$id);
            public function create(array \$data);
            public function update(\$id, array \$data);
            public function delete(\$id);
        }
        PHP;
    }

    // 🏗️ Repository Template
    protected function getRepositoryTemplate($modelClass)
    {
        return <<<PHP
        <?php

        namespace App\Repositories;

        use App\Models\\{$modelClass};
        use App\Repositories\\{$modelClass}RepositoryInterface;

        class {$modelClass}Repository implements {$modelClass}RepositoryInterface
        {
            public function getAll()
            {
                return {$modelClass}::all();
            }

            public function findById(\$id)
            {
                return {$modelClass}::findOrFail(\$id);
            }

            public function create(array \$data)
            {
                return {$modelClass}::create(\$data);
            }

            public function update(\$id, array \$data)
            {
                \$model = {$modelClass}::findOrFail(\$id);
                \$model->update(\$data);
                return \$model;
            }

            public function delete(\$id)
            {
                return {$modelClass}::destroy(\$id);
            }
        }
        PHP;
    }

    // 🏗️ Controller Template
    protected function getControllerTemplate($modelClass)
    {
        $model_repo= Str::lower($modelClass);

        return <<<PHP
        <?php

        namespace App\Http\Controllers;

        use App\Repositories\\{$modelClass}RepositoryInterface;
        use Illuminate\Http\Request;

        class {$modelClass}Controller extends Controller
        {
            protected \${$model_repo}Repository;

            public function __construct({$modelClass}RepositoryInterface \${$model_repo}Repository)
            {
                \$this->{$model_repo}Repository = \${$model_repo}Repository;
            }

            public function index()
            {
                return response()->json(\$this->{$model_repo}Repository->getAll());
            }

            public function show(\$id)
            {
                return response()->json(\$this->{$model_repo}Repository->findById(\$id));
            }

            public function store(Request \$request)
            {
                return response()->json(\$this->{$model_repo}Repository->create(\$request->all()));
            }

            public function update(Request \$request, \$id)
            {
                return response()->json(\$this->{$model_repo}Repository->update(\$id, \$request->all()));
            }

            public function destroy(\$id)
            {
                return response()->json(\$this->{$model_repo}Repository->delete(\$id));
            }
        }
        PHP;
    }
    protected function updateAppServiceProvider($newRepoInterfaceName, $newRepoName)
{
   
    $impexoServiceProvider = 'app/Providers/AppServiceProvider.php';
    $impexoServiceProviderContents = file_get_contents($impexoServiceProvider);
    
    // 🔹 STEP 1: Add "use" statements after "namespace"
    $namespacePosition = strpos($impexoServiceProviderContents, 'namespace');
    $namespaceEndPosition = strpos($impexoServiceProviderContents, ';', $namespacePosition) + 1; // After `namespace`
    $useInterface = "use App\Repositories\\" . $newRepoInterfaceName . ";";
    $useRepo = "use App\Repositories\\" . $newRepoName . ";";
    $addStringTop = "\n$useInterface\n$useRepo\n";
    $updatedContentFirst = substr_replace($impexoServiceProviderContents, $addStringTop, $namespaceEndPosition, 0);
    
    // 🔹 STEP 2: Locate the "register" method and append the binding statement inside it
    $registerStartPosition = strpos($updatedContentFirst, 'public function register()');
    $registerEndPosition = strpos($updatedContentFirst, '}', $registerStartPosition) - 1; // Find before closing `}`
    
    // ✅ Append the new bind statement without removing previous ones
    $addStringBot = '
            $this->app->bind(
                ' . $newRepoInterfaceName . '::class,
                ' . $newRepoName . '::class
            );
    ';
    
    // Insert the new bind statement before the `}` in `register` method
    $updatedContentSecond = substr_replace($updatedContentFirst, $addStringBot, $registerEndPosition, 0);
    
    // 🔹 STEP 3: Save the modified content back to `AppServiceProvider.php`
    if (file_put_contents($impexoServiceProvider, $updatedContentSecond) !== false) {
        $this->line(' ✅ Updated AppServiceProvider successfully.');
    } else {
        $this->error('Cannot update AppServiceProvider.');
    }
    
}

}
