<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    // База тестов — SQLite в памяти (phpunit.xml): на каждый тест миграции с
    // нуля, рабочий MySQL из .env не используется вовсе.
    use RefreshDatabase;

    /**
     * Страховка: вызывается RefreshDatabase до того, как он сносит таблицы.
     * Если phpunit.xml когда-нибудь потеряет DB_CONNECTION=sqlite, тесты
     * упадут здесь, а не очистят рабочую базу.
     */
    protected function beforeRefreshingDatabase()
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException("Тесты должны идти на SQLite в памяти (см. phpunit.xml), а не на {$connection}/{$database}.");
        }
    }
}
