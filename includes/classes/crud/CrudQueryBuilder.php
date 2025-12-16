<?php

namespace Mj\Member\Classes\Crud;

if (!defined('ABSPATH')) {
    exit;
}

class CrudQueryBuilder {
    /** @var string */
    private $table;

    /** @var array<int,string> */
    private $conditions = array('1=1');

    /** @var array<int|string> */
    private $params = array();

    /**
     * @param string $table
     */
    private function __construct($table) {
        $this->table = $table;
    }

    /**
     * @param string $table
     * @return self
     */
    public static function for_table($table) {
        return new self($table);
    }

    /**
     * @param string $column
     * @param array<int|mixed> $values
     * @return $this
     */
    public function where_in_int($column, array $values) {
        $normalized = array_values(array_unique(array_filter(array_map('intval', $values), static function ($value) {
            return $value > 0;
        })));

        if (empty($normalized)) {
            return $this;
        }

        $placeholders = implode(',', array_fill(0, count($normalized), '%d'));
        $this->conditions[] = sprintf('%s IN (%s)', $column, $placeholders);
        $this->params = array_merge($this->params, $normalized);

        return $this;
    }

    /**
     * @param string $column
     * @param array<int|mixed> $values
     * @return $this
     */
    public function where_not_in_int($column, array $values) {
        $normalized = array_values(array_unique(array_filter(array_map('intval', $values), static function ($value) {
            return $value > 0;
        })));

        if (empty($normalized)) {
            return $this;
        }

        $placeholders = implode(',', array_fill(0, count($normalized), '%d'));
        $this->conditions[] = sprintf('%s NOT IN (%s)', $column, $placeholders);
        $this->params = array_merge($this->params, $normalized);

        return $this;
    }

    /**
     * @param string $column
     * @param array<int|string> $values
     * @param callable|null $sanitizer
     * @return $this
     */
    public function where_in_strings($column, array $values, $sanitizer = null) {
        $sanitizer = $sanitizer ?: 'sanitize_text_field';
        $normalized = array();
        foreach ($values as $value) {
            $sanitized = call_user_func($sanitizer, $value);
            if ($sanitized === null) {
                continue;
            }
            $sanitized = (string) $sanitized;
            if ($sanitized === '') {
                continue;
            }
            $normalized[$sanitized] = $sanitized;
        }

        if (empty($normalized)) {
            return $this;
        }

        $normalized = array_values($normalized);
        $placeholders = implode(',', array_fill(0, count($normalized), '%s'));
        $this->conditions[] = sprintf('%s IN (%s)', $column, $placeholders);
        $this->params = array_merge($this->params, $normalized);

        return $this;
    }

    /**
     * @param string $column
     * @param int $value
     * @return $this
     */
    public function where_equals_int($column, $value) {
        $value = (int) $value;
        if ($value <= 0) {
            return $this;
        }

        $this->conditions[] = sprintf('%s = %%d', $column);
        $this->params[] = $value;

        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param callable|null $sanitizer
     * @return $this
     */
    public function where_equals($column, $value, $sanitizer = null) {
        $sanitizer = $sanitizer ?: 'sanitize_text_field';
        $sanitized = call_user_func($sanitizer, $value);
        if ($sanitized === null) {
            return $this;
        }
        $sanitized = (string) $sanitized;
        if ($sanitized === '') {
            return $this;
        }

        $this->conditions[] = sprintf('%s = %%s', $column);
        $this->params[] = $sanitized;

        return $this;
    }

    /**
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @param string $format
     * @return $this
     */
    public function where_compare($column, $operator, $value, $format = '%s') {
        $allowed = array('>=', '<=', '>', '<');
        if (!in_array($operator, $allowed, true)) {
            return $this;
        }

        if ($format === '%d') {
            $value = (int) $value;
        } else {
            $value = sanitize_text_field((string) $value);
        }

        if ($value === '' && $format !== '%d') {
            return $this;
        }

        if ($format === '%d' && (int) $value === 0) {
            return $this;
        }

        $this->conditions[] = sprintf('%s %s %s', $column, $operator, $format);
        $this->params[] = $value;

        return $this;
    }

    /**
     * @param array<int,string> $columns
     * @param string $search
     * @return $this
     */
    public function where_like_any(array $columns, $search) {
        $search = sanitize_text_field((string) $search);
        if ($search === '' || empty($columns)) {
            return $this;
        }

        global $wpdb;
        $like = '%' . $wpdb->esc_like($search) . '%';
        $or_parts = array();
        foreach ($columns as $column) {
            $or_parts[] = sprintf('%s LIKE %%s', $column);
            $this->params[] = $like;
        }

        if (!empty($or_parts)) {
            $this->conditions[] = '(' . implode(' OR ', $or_parts) . ')';
        }

        return $this;
    }

    /**
     * @param array<int,string> $columns
     * @param string $search
     * @return $this
     */
    public function where_tokenized_search(array $columns, $search) {
        $search = sanitize_text_field((string) $search);
        if ($search === '' || empty($columns)) {
            return $this;
        }

        $tokens = preg_split('/[\s,]+/', $search);
        if (!is_array($tokens)) {
            return $this;
        }

        global $wpdb;
        $token_clauses = array();
        foreach ($tokens as $token) {
            $token = sanitize_text_field((string) $token);
            if ($token === '') {
                continue;
            }

            $like = '%' . $wpdb->esc_like($token) . '%';
            $local = array();
            foreach ($columns as $column) {
                $local[] = sprintf('%s LIKE %%s', $column);
                $this->params[] = $like;
            }

            if (!empty($local)) {
                $token_clauses[] = '(' . implode(' OR ', $local) . ')';
            }
        }

        if (!empty($token_clauses)) {
            $this->conditions[] = '(' . implode(' AND ', $token_clauses) . ')';
        }

        return $this;
    }

    /**
     * @param string $clause
     * @param array<int|string> $params
     * @return $this
     */
    public function where_raw($clause, array $params = array()) {
        $clause = trim((string) $clause);
        if ($clause === '') {
            return $this;
        }

        $this->conditions[] = $clause;
        if (!empty($params)) {
            foreach ($params as $param) {
                $this->params[] = $param;
            }
        }

        return $this;
    }

    /**
     * @param string $columns
     * @param string $order_by
     * @param string $order
     * @param int $limit
     * @param int $offset
     * @return array{0:string,1:array<int|string>}
     */
    public function build_select($columns = '*', $order_by = '', $order = 'ASC', $limit = 0, $offset = 0) {
        $sql = sprintf('SELECT %s FROM %s WHERE %s', $columns, $this->table, implode(' AND ', $this->conditions));

        $order_by = trim((string) $order_by);
        if ($order_by !== '') {
            $direction = strtoupper((string) $order);
            $direction = $direction === 'ASC' ? 'ASC' : 'DESC';
            $sql .= sprintf(' ORDER BY %s %s', $order_by, $direction);
        }

        $limit = (int) $limit;
        $offset = max(0, (int) $offset);
        if ($limit > 0) {
            $sql .= ' LIMIT %d';
            $this->params[] = $limit;
            if ($offset > 0) {
                $sql .= ' OFFSET %d';
                $this->params[] = $offset;
            }
        }

        return array($sql, $this->params);
    }

    /**
     * @param string $column
     * @return array{0:string,1:array<int|string>}
     */
    public function build_count($column = '*') {
        $sql = sprintf('SELECT COUNT(%s) FROM %s WHERE %s', $column, $this->table, implode(' AND ', $this->conditions));
        return array($sql, $this->params);
    }
}
