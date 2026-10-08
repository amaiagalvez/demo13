<?php

namespace Tests\Unit\Support\Database;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class DatabaseHelpersTest extends TestCase
{
    #[DataProvider('databaseDrivers')]
    public function test_detects_sqlite_or_pgsql_on_the_default_connection(string $driver, bool $expected): void
    {
        $originalDefault = config('database.default');
        $originalConnection = config('database.connections.helper-test');

        try {
            config([
                'database.default' => 'helper-test',
                'database.connections.helper-test' => [
                    'driver' => $driver,
                    'database' => ':memory:',
                ],
            ]);

            $result = isSqliteOrPgsql();

            $this->assertSame($expected, $result);
        } finally {
            config([
                'database.default' => $originalDefault,
                'database.connections.helper-test' => $originalConnection,
            ]);
        }
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function databaseDrivers(): array
    {
        return [
            'sqlite' => ['sqlite', true],
            'postgresql' => ['pgsql', true],
            'mysql' => ['mysql', false],
            'mariadb' => ['mariadb', false],
            'sql-server' => ['sqlsrv', false],
        ];
    }
}
