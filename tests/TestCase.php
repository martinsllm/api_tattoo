<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstNonSqliteDatabase();
    }

    /**
     * Aborta a suite se o driver default não for sqlite.
     *
     * Defesa em profundidade: mesmo que o phpunit.xml seja ignorado por uma
     * env exportada no shell ou por config:cache desatualizado, os testes
     * nunca rodam contra o banco de desenvolvimento/produção.
     */
    private function guardAgainstNonSqliteDatabase(): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver !== 'sqlite') {
            throw new RuntimeException(
                "Testes só podem rodar em SQLite. Driver detectado: '{$driver}' (conexão '{$connection}'). ".
                'Verifique phpunit.xml, .env e se há config cacheada (php artisan config:clear).'
            );
        }
    }
}
