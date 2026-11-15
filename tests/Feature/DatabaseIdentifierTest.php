<?php

namespace Tests\Feature;

use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class DatabaseIdentifierTest extends TestCase
{
    public function test_migration_identifiers_fit_mysql_limits(): void
    {
        config(['database.connections.identifier_audit' => [
            'driver' => 'mysql',
            'database' => 'identifier_audit',
            'prefix' => '',
        ]]);

        DB::extend('identifier_audit', function () {
            return new MySqlConnection(
                new PDO('sqlite::memory:'),
                'identifier_audit',
                '',
                ['driver' => 'mysql', 'name' => 'identifier_audit']
            );
        });

        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection('identifier_audit');

        try {
            $connection = DB::connection('identifier_audit');
            $connection->useDefaultSchemaGrammar();

            $queries = $connection->pretend(function () {
                $migration = require database_path('migrations/0001_01_01_000003_create_nextrespack_tables.php');
                $migration->up();
            });

            $sql = implode("\n", array_column($queries, 'query'));
            $this->assertNotSame('', $sql);

            preg_match_all('/constraint\s+`([^`]+)`/i', $sql, $constraints);
            preg_match_all('/add\s+(?:unique\s+|index\s+)?`([^`]+)`/i', $sql, $indexes);

            $names = array_values(array_unique(array_merge(
                $constraints[1] ?? [],
                $indexes[1] ?? [],
            )));

            $this->assertContains('mcpf_versions_format_type_index', $names);

            foreach ($names as $name) {
                $this->assertLessThanOrEqual(
                    64,
                    strlen($name),
                    "Database identifier [{$name}] exceeds the MySQL 64 character limit."
                );
            }

            $duplicates = array_diff_assoc(
                array_merge($constraints[1] ?? [], $indexes[1] ?? []),
                array_unique(array_merge($constraints[1] ?? [], $indexes[1] ?? []))
            );

            $this->assertSame([], array_values(array_unique($duplicates)), 'Duplicate index or constraint names were generated.');
        } finally {
            DB::setDefaultConnection($previous);
        }
    }
}
