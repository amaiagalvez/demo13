<?php

namespace Tests\Feature\Support\Database;

use Tests\TestCase;
use App\Models\User;
use Tests\DuskTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;

class DatabaseHelpersTest extends TestCase
{
    /**
     * This class creates and drops real tables, so it must never point at a database worth
     * keeping. It deliberately carries none of the database traits: MariaDB commits implicitly on
     * CREATE TABLE, which would commit the transaction a RefreshDatabase wraps this test in.
     *
     * @see DuskTestCase::setUp() for the same guard on the browser suite.
     */
    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame('laravel_test', config('database.connections.mysql.database'));
    }

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

    public function test_adds_the_audit_columns_to_a_table(): void
    {
        Schema::dropIfExists('helper_audit_columns');
        Schema::create('helper_audit_columns', function (Blueprint $table): void {
            $table->id();

            addAuditColumns($table);
        });

        $this->assertTrue(Schema::hasColumns('helper_audit_columns', [
            'created_by', 'updated_by', 'deleted_by',
        ]));

        $author = User::factory()->create();
        $id = DB::table('helper_audit_columns')->insertGetId(['created_by' => $author->id]);

        // Every key is nullable, so a row written without an actor exists instead of failing.
        $anonymousId = DB::table('helper_audit_columns')->insertGetId([]);

        $this->assertSame(
            $author->id,
            DB::table('helper_audit_columns')->where('id', $id)->value('created_by'),
        );
        $this->assertNull(DB::table('helper_audit_columns')->where('id', $anonymousId)->value('created_by'));

        try {
            $author->forceDelete();
            self::fail('A user with audit references must not be force deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('users', ['id' => $author->id]);
        }

        $this->assertSame(
            $author->id,
            DB::table('helper_audit_columns')->where('id', $id)->value('created_by'),
        );

        Schema::drop('helper_audit_columns');
    }
}
