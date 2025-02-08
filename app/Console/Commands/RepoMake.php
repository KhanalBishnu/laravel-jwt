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

        // 5️⃣ Bind Interface to Repository in AppServiceProvider
        $this->updateAppServiceProvider($modelClass);
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
        return <<<PHP
        <?php

        namespace App\Http\Controllers;

        use App\Repositories\\{$modelClass}RepositoryInterface;
        use Illuminate\Http\Request;

        class {$modelClass}Controller extends Controller
        {
            protected \${$modelClass}Repository;

            public function __construct({$modelClass}RepositoryInterface \${$modelClass}Repository)
            {
                \$this->{$modelClass}Repository = \${$modelClass}Repository;
            }

            public function index()
            {
                return response()->json(\$this->{$modelClass}Repository->getAll());
            }

            public function show(\$id)
            {
                return response()->json(\$this->{$modelClass}Repository->findById(\$id));
            }

            public function store(Request \$request)
            {
                return response()->json(\$this->{$modelClass}Repository->create(\$request->all()));
            }

            public function update(Request \$request, \$id)
            {
                return response()->json(\$this->{$modelClass}Repository->update(\$id, \$request->all()));
            }

            public function destroy(\$id)
            {
                return response()->json(\$this->{$modelClass}Repository->delete(\$id));
            }
        }
        PHP;
    }

    // 🔗 Update AppServiceProvider for Binding
    protected function updateAppServiceProvider($modelClass)
    {
        $serviceProviderPath = app_path('Providers/AppServiceProvider.php');
        $interfaceNamespace = "App\\Repositories\\Interfaces\\{$modelClass}RepositoryInterface";
        $repositoryNamespace = "App\\Repositories\\{$modelClass}Repository";

        // Read existing file
        $content = File::get($serviceProviderPath);

        // Check if binding already exists
        if (Str::contains($content, "{$interfaceNamespace}::class")) {
            $this->warn("⚠️ Binding already exists in AppServiceProvider");
            return;
        }

        // Insert binding into register() method
        $binding = "\$this->app->bind({$interfaceNamespace}::class, {$repositoryNamespace}::class);";

        if (Str::contains($content, 'public function register()')) {
            // Insert after "public function register() {"
            $updatedContent = preg_replace('/public function register\(\)\s*{/', "public function register()\n    {\n        {$binding}", $content);
        } else {
            // Append at the end
            $updatedContent = str_replace("}", "\n    public function register()\n    {\n        {$binding}\n    }\n}", $content);
        }

        File::put($serviceProviderPath, $updatedContent);
        $this->info("✅ AppServiceProvider updated with repository binding");
    }
}
