<?php

namespace Tests\Feature\Support\Database;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
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
        $this->assertEquals(1, $records->where('id', $id)->value('active'));
        $this->assertNull($records->where('id', $id)->value('deleted_at'));
        $this->assertTrue(Schema::hasIndex('helper_columns', ['deleted_at', 'id']));

        Schema::drop('helper_columns');
    }

    public function test_rejects_a_second_undeleted_row_repeating_a_name(): void
    {
        $records = $this->createTableWithActiveNameUniqueness();
        $records->insert(['name' => 'Taken name']);

        $this->assertThrows(
            fn () => $records->insert(['name' => 'Taken name']),
            QueryException::class,
        );

        Schema::drop('helper_active_names');
    }

    public function test_accepts_a_deleted_row_repeating_an_undeleted_name(): void
    {
        $records = $this->createTableWithActiveNameUniqueness();
        $records->insert(['name' => 'Traded name']);

        $records->update(['deleted_at' => now()]);
        $records->insert(['name' => 'Traded name']);

        $this->assertDatabaseCount('helper_active_names', 2);

        Schema::drop('helper_active_names');
    }

    public function test_scopes_the_name_uniqueness_to_the_given_columns(): void
    {
        $records = $this->createTableWithActiveNameUniqueness(['project_id']);
        $records->insert(['project_id' => 1, 'name' => 'Sprint 1']);
        $records->insert(['project_id' => 2, 'name' => 'Sprint 1']);

        $this->assertThrows(
            fn () => $records->insert(['project_id' => 1, 'name' => 'Sprint 1']),
            QueryException::class,
        );

        Schema::drop('helper_active_names');
    }

    public function test_names_the_unique_index_after_the_table_and_its_scope(): void
    {
        $this->createTableWithActiveNameUniqueness(['project_id']);

        $this->assertTrue(Schema::hasIndex('helper_active_names', 'helper_active_names_project_id_active_name_unique'));

        Schema::drop('helper_active_names');
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

    /**
     * A soft-deleting table whose name uniqueness is added the way the migrations add it.
     *
     * @param  list<string>  $scope
     */
    private function createTableWithActiveNameUniqueness(array $scope = []): Builder
    {
        Schema::dropIfExists('helper_active_names');
        Schema::create('helper_active_names', function (Blueprint $table) use ($scope): void {
            $table->id();

            foreach ($scope as $column) {
                $table->unsignedBigInteger($column);
            }

            $table->string('name');

            addCommonColumns($table);
        });

        addUniqueActiveNameIndex('helper_active_names', $scope);

        return DB::table('helper_active_names');
    }
}
