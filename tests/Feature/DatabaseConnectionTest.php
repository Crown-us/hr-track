<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    public function test_test_database_is_mysql_and_has_migrated_tables(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('hris_lite_test', DB::selectOne('SELECT DATABASE() AS db')->db);
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('sessions'));
    }

    public function test_test_account_cannot_read_the_development_database(): void
    {
        try {
            DB::select('SELECT id FROM hris_lite.users LIMIT 1');
            $this->fail('Test account must not have access to development data.');
        } catch (QueryException $exception) {
            $this->assertSame(1142, $exception->errorInfo[1]);
        }
    }
}
