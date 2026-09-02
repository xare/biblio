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
        try {
            return (int) $wpdb->get_var("SELECT COUNT(id) FROM {$table_name}");
        } catch (Exception $e) {
            $this->logger?->error(__METHOD__, "Count failed: " . $e->getMessage());
            return 0;
        }
    }

    protected function insert(array $data, string $table_suffix, array $format = null): int
    {
        global $wpdb;
        $table_name = $this->getTableName($table_suffix);

        if ($format === null) {
            $format = array_fill(0, count($data), '%s');
        }

        try {
            $wpdb->insert($table_name, $data, $format);
            return (int) $wpdb->insert_id;
        } catch (Exception $e) {
            $this->logger?->error(__METHOD__, "Insert failed: " . $e->getMessage());
            return 0;
        }
    }

    protected function update(int $id, array $data, string $table_suffix, array $format = null): bool
    {
        global $wpdb;
        $table_name = $this->getTableName($table_suffix);

        if ($format === null) {
            $format = array_fill(0, count($data), '%s');
        }

        try {
            return (bool) $wpdb->update($table_name, $data, ['id' => $id], $format, ['%d']);
        } catch (Exception $e) {
            $this->logger?->error(__METHOD__, "Update failed: " . $e->getMessage());
            return false;
        }
    }

    protected function delete(int $id, string $table_suffix): bool
    {
        global $wpdb;
        $table_name = $this->getTableName($table_suffix);

        try {
            return (bool) $wpdb->delete($table_name, ['id' => $id], ['%d']);
        } catch (Exception $e) {
            $this->logger?->error(__METHOD__, "Delete failed: " . $e->getMessage());
            return false;
        }
    }

    protected function truncate(string $table_suffix): bool
    {
        global $wpdb;
        $table_name = $this->getTableName($table_suffix);

        try {
            return (bool) $wpdb->query("TRUNCATE TABLE {$table_name}");
        } catch (Exception $e) {
            $this->logger?->error(__METHOD__, "Truncate failed: " . $e->getMessage());
            return false;
        }
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