<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Databases the test suite is allowed to connect to. RefreshDatabase wipes
     * the connected database on every run, so we hard-stop if it ever resolves
     * to anything that is not an explicit, throwaway test database.
     *
     * @var array<int, string>
     */
    protected array $allowedTestDatabases = [
        'erp_testing_new',
        'erp_testing',
        ':memory:',
    ];

    /**
     * Boot the application, then refuse to continue if it is pointed at a
     * non-test database. This runs inside createApplication(), i.e. BEFORE
     * RefreshDatabase migrates, so a misconfigured env can never wipe real data.
     */
    public function createApplication(): Application
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->guardAgainstNonTestDatabase($app);

        return $app;
    }

    protected function guardAgainstNonTestDatabase(Application $app): void
    {
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if (in_array($database, $this->allowedTestDatabases, true)) {
            return;
        }

        $line = str_repeat('*', 72);
        fwrite(STDERR, PHP_EOL.$line.PHP_EOL);
        fwrite(STDERR, "ABORTING TEST RUN — refusing to run against database '{$database}'.".PHP_EOL);
        fwrite(STDERR, 'RefreshDatabase would DROP every table on this connection.'.PHP_EOL);
        fwrite(STDERR, 'Allowed test databases: '.implode(', ', $this->allowedTestDatabases).PHP_EOL);
        fwrite(STDERR, 'Fix: ensure phpunit.xml <env DB_DATABASE force="true"> is applied'.PHP_EOL);
        fwrite(STDERR, '(the container exports a real DB_DATABASE that must be overridden).'.PHP_EOL);
        fwrite(STDERR, $line.PHP_EOL.PHP_EOL);

        exit(1);
    }
}
