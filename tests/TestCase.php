<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Factory users share the default password, so the forced-change redirect
     * is off unless a test opts in.
     */
    protected bool $enforcePasswordChange = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->enforcePasswordChange) {
            $this->withoutMiddleware(\App\Http\Middleware\EnforcePasswordChange::class);
        }
    }

    public function createApplication()
    {
        $app = parent::createApplication();

        // Fail closed before any test can migrate or write to a real database.
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || $app['config']->get('database.connections.sqlite.url')) {
            throw new \RuntimeException('Tests require isolated SQLite :memory: storage.');
        }

        return $app;
    }
}
