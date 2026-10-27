<?php

namespace App\Updates\Backup;

use App\Updates\Deadline;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * A pure-PHP, resumable SQL dump for hosts without mysqldump. Every statement
 * is written on one line, and values are hex literals, so restore can replay
 * the file line by line without parsing SQL.
 */
final class DatabaseDump
{
    public function __construct(private readonly string $connection) {}

    /**
     * Continue the dump into $file until it finishes or the deadline passes.
     * Returns the new state; 'done' is true once the dump is complete.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function run(string $file, array $state, Deadline $deadline, int $rowsPerQuery): array
    {
        $db = DB::connection($this->connection);
        $state['tables'] ??= $this->tables($db);
        $state['table'] ??= 0;
        $state['offset'] ??= 0;

        if (! is_file($file)) {
            file_put_contents($file, "-- NPO.WorkDash backup\nSET FOREIGN_KEY_CHECKS=0;\n");
        }

        $handle = fopen($file, 'ab');

        try {
            while ($state['table'] < count($state['tables'])) {
                $table = $state['tables'][$state['table']];

                if ($state['offset'] === 0) {
                    fwrite($handle, $this->schema($db, $table));
                }

                $query = $db->table($table)->offset($state['offset'])->limit($rowsPerQuery);

                foreach ($db->select("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'") as $key) {
                    $query->orderBy($key->Column_name);
                }

                $rows = $query->get()->map(fn ($row) => (array) $row)->all();

                if ($rows !== []) {
                    fwrite($handle, $this->insert($table, $rows, $this->numericColumns($db, $table)));
                    $state['offset'] += count($rows);
                }

                if (count($rows) < $rowsPerQuery) {
                    $state['table']++;
                    $state['offset'] = 0;
                }

                if ($deadline->passed()) {
                    return [...$state, 'done' => false];
                }
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }

        return [...$state, 'done' => true];
    }

    /**
     * Replay a dump: drop and recreate every table in it, then insert its rows.
     */
    public function restore(string $file): void
    {
        $db = DB::connection($this->connection);
        $handle = fopen($file, 'rb');

        try {
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");

                if ($line !== '' && ! str_starts_with($line, '--')) {
                    $db->unprepared($line);
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return list<string>
     */
    private function tables(Connection $db): array
    {
        $exclude = config('updates.backup_exclude', []);
        $tables = array_map(fn ($row) => array_values((array) $row)[0], $db->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"));

        return array_values(array_diff($tables, $exclude));
    }

    private function schema(Connection $db, string $table): string
    {
        $create = array_values((array) $db->selectOne("SHOW CREATE TABLE `{$table}`"))[1];

        return "DROP TABLE IF EXISTS `{$table}`;\n".str_replace(["\r\n", "\n"], ' ', (string) $create).";\n";
    }

    /**
     * Columns whose values are written as plain numbers. MySQL reads a hex
     * literal in a numeric column as an integer, so these must not be hex.
     *
     * @return list<string>
     */
    private function numericColumns(Connection $db, string $table): array
    {
        $numeric = '/^(tinyint|smallint|mediumint|int|integer|bigint|decimal|numeric|float|double|real|year)\b/i';

        return array_values(array_map(
            fn ($column) => $column->Field,
            array_filter($db->select("SHOW COLUMNS FROM `{$table}`"), fn ($column) => preg_match($numeric, $column->Type) === 1),
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $numeric
     */
    private function insert(string $table, array $rows, array $numeric): string
    {
        $columns = '`'.implode('`,`', array_keys($rows[0])).'`';
        $values = array_map(function (array $row) use ($numeric) {
            $literals = [];

            foreach ($row as $column => $value) {
                $literals[] = self::literal($value, in_array($column, $numeric, true));
            }

            return '('.implode(',', $literals).')';
        }, $rows);

        return "INSERT INTO `{$table}` ({$columns}) VALUES ".implode(',', $values).";\n";
    }

    private static function literal(mixed $value, bool $numeric): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if ($numeric && is_numeric($value)) {
            return (string) $value;
        }

        return (string) $value === '' ? "''" : "X'".bin2hex((string) $value)."'";
    }
}
