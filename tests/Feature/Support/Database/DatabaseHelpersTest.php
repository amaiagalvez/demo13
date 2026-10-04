<?php

namespace Tests\Feature\Support\Database;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\DataProvider;

class DatabaseHelpersTest extends TestCase
{
    public function test_adds_the_common_columns_to_a_table(): void
    {
        Schema::dropIfExists('helper_columns');
        Schema::create('helper_columns', function (Blueprint $table): void {
            $table->id();

            addCommonColumns($table);
        });

        $records = DB::table('helper_columns');
        $id = $records->insertGetId(['notes' => 'first notes']);

        $this->assertTrue(Schema::hasColumns('helper_columns', [
            'notes', 'active', 'created_at', 'updated_at', 'deleted_at',
        ]));
        $this->assertSame('first notes', $records->where('id', $id)->value('notes'));
        $this->assertEquals(1, $records->where('id', $id)->value('active'));
        $this->assertNull($records->where('id', $id)->value('deleted_at'));
        $this->assertTrue(Schema::hasIndex('helper_columns', ['deleted_at', 'id']));

        // Notes are nullable, so an insert that leaves the column out still succeeds.
        $secondId = $records->insertGetId(['active' => false]);
        $this->assertNull($records->where('id', $secondId)->value('notes'));

        Schema::drop('helper_columns');
    }

    #[DataProvider('databaseDrivers')]
    public function test_detects_sqlite_or_pgsql_on_the_default_connection(string $driver, bool $expected): void
    {
        config([
            'database.default' => 'helper-test',
            'database.connections.helper-test' => [
                'driver' => $driver,
                'database' => ':memory:',
            ],
        ]);

        $result = isSqliteOrPgsql();

        $this->assertSame($expected, $result);
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
