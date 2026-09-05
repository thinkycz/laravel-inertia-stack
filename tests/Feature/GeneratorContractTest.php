<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Symfony\Component\Process\Process;
use Thinkycz\LaravelCore\Support\Resolver;

\test('a disposable generated CRUD slice enforces ownership and bounded pagination', function (): void {
    $basePath = $this->app->basePath();
    $appPath = $this->app->path();
    $databasePath = $this->app->databasePath();
    $sandbox = \sys_get_temp_dir() . '/starter-generator-' . \bin2hex(\random_bytes(8));
    $files = new Filesystem();
    $files->makeDirectory($sandbox . '/app/Models', 0o755, true);
    $files->makeDirectory($sandbox . '/routes', 0o755, true);
    $files->makeDirectory($sandbox . '/database/migrations', 0o755, true);
    $files->copyDirectory($basePath . '/stubs', $sandbox . '/stubs');
    $files->copy($basePath . '/composer.json', $sandbox . '/composer.json');
    $files->put($sandbox . '/routes/api.php', '<?php use Illuminate\\Routing\\Router; use Thinkycz\\LaravelCore\\Support\\Resolver;' . "\n");

    $loader = static function (string $class) use ($sandbox): void {
        foreach (['App\\' => '/app/', 'Database\\Factories\\' => '/database/factories/'] as $prefix => $directory) {
            if (\str_starts_with($class, $prefix)) {
                $file = $sandbox . $directory . \str_replace('\\', '/', \mb_substr($class, \mb_strlen($prefix))) . '.php';
                if (\is_file($file)) {
                    require_once $file;
                }
            }
        }
    };
    \spl_autoload_register($loader);

    try {
        $this->app->setBasePath($sandbox);
        $this->app->useAppPath($sandbox . '/app');
        $this->app->useDatabasePath($sandbox . '/database');
        $this->artisan('make:crud', ['name' => 'AuditWidget', '--no-interaction' => true])->assertSuccessful();
        $this->app->setBasePath($basePath);
        $this->app->useAppPath($appPath);
        $this->app->useDatabasePath($databasePath);

        foreach (['Index', 'Show', 'Store', 'Update', 'Destroy'] as $action) {
            \expect($files->exists($sandbox . '/app/Http/Controllers/Api/AuditWidget/AuditWidget' . $action . 'Controller.php'))->toBeTrue();
            \expect($files->exists($sandbox . '/tests/Feature/App/Http/Controllers/Api/AuditWidget/AuditWidget' . $action . 'ControllerTest.php'))->toBeTrue();
        }
        $autoload = "<?php\n";
        foreach ([$sandbox . '/app', $sandbox . '/database/factories'] as $directory) {
            foreach ($files->allFiles($directory) as $file) {
                $autoload .= 'require_once ' . \var_export($file->getPathname(), true) . ";\n";
            }
        }
        foreach ($files->allFiles($sandbox) as $file) {
            if ($file->getExtension() === 'php') {
                \token_get_all($files->get($file->getPathname()), \TOKEN_PARSE);
            }
        }
        $files->put($sandbox . '/autoload.php', $autoload);
        $analysis = new Process([\PHP_BINARY, $basePath . '/vendor/bin/phpstan', 'analyse',
            '--configuration=' . $basePath . '/phpstan.neon', '--autoload-file=' . $sandbox . '/autoload.php',
            '--no-progress', '--error-format=raw', $sandbox . '/app', $sandbox . '/database',
        ], $basePath);
        $analysis->setTimeout(120);
        $analysis->run();
        $this->assertTrue($analysis->isSuccessful(), $analysis->getOutput() . $analysis->getErrorOutput());

        foreach ($files->glob($sandbox . '/database/migrations/*.php') as $migration) {
            (require $migration)->up();
        }
        Resolver::resolveRouteRegistrar()->prefix('generated')->middleware('api')->group(static function (Router $router) use ($sandbox): void {
            require $sandbox . '/routes/api.php';
        });
        Resolver::resolveRouter()->getRoutes()->refreshNameLookups();
        Resolver::resolveRouter()->getRoutes()->refreshActionLookups();

        $url = '/generated/v1/audit_widgets/';
        foreach (['index', 'show'] as $action) {
            $this->getJson($url . $action . '?id=1')->assertUnauthorized();
        }
        foreach (['store', 'update', 'destroy'] as $action) {
            $this->postJson($url . $action, ['id' => 1, 'title' => 'Guest'])->assertUnauthorized();
        }

        $owner = UserFactory::new()->createOne();
        $other = UserFactory::new()->createOne();
        \expect($owner)->toBeInstanceOf(User::class);
        $this->be($owner);
        $created = $this->postJson($url . 'store', ['title' => 'Owner title'])->assertCreated();
        $id = $created->json('data.id');
        $this->getJson($url . 'show?id=' . $id)->assertOk()->assertJsonPath('data.attributes.title', 'Owner title');
        $this->postJson($url . 'update', ['id' => $id, 'title' => 'Updated'])->assertNoContent();
        $this->getJson($url . 'show?id=' . $id)->assertJsonPath('data.attributes.title', 'Updated');
        $this->postJson($url . 'update', ['id' => $id, 'title' => null])->assertUnprocessable();
        $this->postJson($url . 'update', ['id' => $id])->assertNoContent();

        $this->be($other);
        $this->getJson($url . 'show?id=' . $id)->assertNotFound();
        $this->postJson($url . 'update', ['id' => $id, 'title' => 'Stolen'])->assertNotFound();
        $this->postJson($url . 'destroy', ['id' => $id])->assertNotFound();
        $this->getJson($url . 'index')->assertJsonCount(0, 'data');
        $this->be($owner);
        $factory = 'Database\\Factories\\AuditWidgetFactory';
        $factory::new()->for($owner)->count(104)->create();
        $this->getJson($url . 'index')->assertOk()->assertJsonCount(25, 'data');
        $this->getJson($url . 'index?take=100')->assertOk()->assertJsonCount(100, 'data');
        $this->getJson($url . 'index?take=101')->assertUnprocessable();
        $this->getJson($url . 'index?take=0')->assertUnprocessable();
        $this->postJson($url . 'destroy', ['id' => $id])->assertNoContent();
        $this->getJson($url . 'show?id=' . $id)->assertNotFound();
    } finally {
        $this->app->setBasePath($basePath);
        $this->app->useAppPath($appPath);
        $this->app->useDatabasePath($databasePath);
        \spl_autoload_unregister($loader);
        $files->deleteDirectory($sandbox);
    }
});
