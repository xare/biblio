<?php

namespace Inc\Biblio\Api;

use Exception;

trait DbOperationsTrait
{
    protected string $table_suffix;

    protected function getTableName(string $table_suffix): string
    {
        global $wpdb;
        return $wpdb->prefix . $table_suffix;
    }

    protected function count(string $table_suffix): int
    {
        global $wpdb;
        $table_name = $this->getTableName($table_suffix);
        return (int) DatabaseGuard::guard($wpdb->get_var("SELECT COUNT(id) FROM {$table_name}"), __METHOD__, 'biblio');
    }

    protected function insert(array $data, string $table_suffix, array $format = null): int
    {
        global $wpdb;
        $table_name = $this->getTableName($table_suffix);

        if ($format === null) {
            $format = array_fill(0, count($data), '%s');
        }

        DatabaseGuard::guard($wpdb->insert($table_name, $data, $format), __METHOD__, 'biblio');
        return (int) $wpdb->insert_id;
    }

    protected function update(int $id, array $data, string $table_suffix, array $format = null): bool
    {
        global $wpdb;
        $table_name = $this->getTableName($table_suffix);

        if ($format === null) {
            $format = array_fill(0, count($data), '%s');
        }

        $result = DatabaseGuard::guard(
            $wpdb->update($table_name, $data, ['id' => $id], $format, ['%d']),
            __METHOD__,
            'biblio'
        );
        return false !== $result;
    }

    protected function delete(int $id, string $table_suffix): bool
    {
        global $wpdb;
        $table_name = $this->getTableName($table_suffix);

        $result = DatabaseGuard::guard(
            $wpdb->delete($table_name, ['id' => $id], ['%d']),
            __METHOD__,
            'biblio'
        );
        return false !== $result;
    }

    protected function truncate(string $table_suffix): bool
    {
        global $wpdb;
        $table_name = $this->getTableName($table_suffix);

        $result = DatabaseGuard::guard(
            $wpdb->query("TRUNCATE TABLE {$table_name}"),
            __METHOD__,
            'biblio'
        );
        return false !== $result;
    }

    protected function select(string $table_suffix, array $conditions = [], string $columns = '*'): array
    {
        global $wpdb;
        $table_name = $this->getTableName($table_suffix);

        if (empty($conditions)) {
            return (array) $wpdb->get_results("SELECT {$columns} FROM {$table_name}", ARRAY_A);
        }

        $where = [];
        $values = [];
        foreach ($conditions as $column => $value) {
            $where[] = "{$column} = %s";
            $values[] = $value;
        }

        $sql = "SELECT {$columns} FROM {$table_name} WHERE " . implode(' AND ', $where);
        return (array) $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
    }
}