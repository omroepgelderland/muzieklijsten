<?php

declare(strict_types=1);

namespace muzieklijsten;

use gldstdlib\exception\GLDException;

/**
 * Serverside processing voor DataTables.
 *
 * Alleen voor queries op de tabel nummers.
 *
 * @see http://datatables.net/usage/server-side for full details on the server-
 * side processing requirements of DataTables.
 *
 * @phpstan-type SearchType array{
 *     value: string,
 *     regex: bool,
 * }
 */
final class SSP
{
    /**
     * Kolommen die opgevraagd mogen worden.
     */
    private const COLUMN_FILTER = [
        'id',
        'muziek_id',
        'titel',
        'artiest',
        'jaar',
        'categorie',
        'map',
        'opener',
        'dir',
        'is_vrijekeuze',
    ];
    private const LEGACY_COLUMNS = [
        'id',
        'titel',
        'artiest',
        'jaar',
    ];

    /**
     * Invoer moet van tevoren getypecast zijn. SQL string-escape gebeurt hier.
     *
     * @param SearchType $search
     * @param array<int, array{
     *     column: int,
     *     dir: string,
     *     name: string,
     * }> $order
     * @param array<int, array{
     *     data: string,
     *     name: string,
     *     searchable: bool,
     *     orderable: bool,
     *     search: SearchType,
     * }> $columns
     */
    public function __construct(
        private readonly DB $db,
        private readonly int $draw,
        private readonly int $start,
        private readonly int $length,
        private array $search,
        private array $order,
        private array $columns,
        private readonly ?Lijst $lijst = null,
        private readonly bool $random_volgorde = false,
        private readonly ?bool $is_vrijekeuze = null,
        private readonly ?int $random_seed = null,
    ) {
        $this->search['value'] = $this->db->escape_like($this->search['value']);
        foreach ($this->columns as $i => $column) {
            $column_data = $column['data'];
            if (!\in_array($column_data, self::COLUMN_FILTER, true)) {
                if (\in_array($column['name'], self::COLUMN_FILTER, true)) {
                    $column_data = $column['name'];
                } elseif (\ctype_digit($column_data) && isset(self::LEGACY_COLUMNS[(int)$column_data])) {
                    $column_data = self::LEGACY_COLUMNS[(int)$column_data];
                } elseif (\ctype_digit($column['name']) && isset(self::LEGACY_COLUMNS[(int)$column['name']])) {
                    $column_data = self::LEGACY_COLUMNS[(int)$column['name']];
                }
            }

            if (!\in_array($column_data, self::COLUMN_FILTER, true)) {
                throw new GLDException("Invalid column: {$column['data']}");
            }
            $this->columns[$i]['data'] = "n.`{$column_data}`";
            $this->columns[$i]['search']['value'] = $this->db->escape_like($column['search']['value']);
        }
        if ($this->random_volgorde) {
            $this->order = [];
        } else {
            foreach ($this->order as $i => $orderItem) {
                $this->order[$i]['name'] = "n.`{$this->db->escape_string($orderItem['name'])}`";
            }
        }
    }

    /**
     * Create the data output array for the DataTables rows
     *
     * @param $data Data from the SQL get
     *
     * @return array<array<string, string>> Formatted data in a row based format
     */
    private function data_output(\mysqli_result $data): array
    {
        return array_values(iterator_to_array($data));
    }

    /**
     * Construct the SELECT clause.
     */
    private function select(): string
    {
        $columnNames = [
            'n.`id` AS DT_RowId',
            ...array_map(static fn($col) => $col['data'], $this->columns),
        ];
        return 'SELECT ' . implode(', ', $columnNames);
    }

    /**
     * Paging
     *
     * Construct the LIMIT clause for server-side processing SQL query
     */
    private function limit(): string
    {
        if ($this->length === -1) {
            return '';
        }
        return "LIMIT {$this->start}, {$this->length}";
    }

    /**
     * Ordering
     *
     * Construct the ORDER BY clause for server-side processing SQL query
     *
     * @return string SQL order by clause
     */
    private function order(): string
    {
        if ($this->random_volgorde) {
            $seed = $this->random_seed === null ? "" : (string)$this->random_seed;
            return "ORDER BY RAND({$seed})";
        }

        $orderBy = [];
        foreach ($this->order as $order) {
            $column = $this->columns[$order['column']];

            if (!\in_array($order['dir'], ['asc', 'desc'], true)) {
                throw new GLDException("Invalid order direction: {$order['dir']}");
            }
            $dir = $order['dir'] === 'asc' ? 'ASC' : 'DESC';

            $orderBy[] = "{$column['data']} {$dir}";
        }

        if (\count($orderBy) === 0) {
            return '';
        }
        return 'ORDER BY ' . implode(', ', $orderBy);
    }

    /**
     * Searching / Filtering
     *
     * Construct the WHERE clause for server-side processing SQL query.
     *
     * @return string SQL where clause
     */
    private function filter(): string
    {
        $search_value = $this->db->escape_string($this->search['value']);
        $globalSearch = [];
        $columnSearch = [];

        if ($this->is_vrijekeuze !== null) {
            $columnSearch[] = \sprintf(
                'n.is_vrijekeuze = %d',
                $this->is_vrijekeuze,
            );
        }

        foreach ($this->columns as $column) {
            $column_search_value = $column['search']['value'];

            // Global search filtering
            if ($column['searchable'] && $search_value != '') {
                $globalSearch[] = "{$column['data']} LIKE \"%{$search_value}%\"";
            }
            // Individual column filtering
            if ($column['searchable'] && $column_search_value != '') {
                $columnSearch[] = "{$column['data']} LIKE \"%{$column_search_value}%\"";
            }
        }

        // Combine the filters into a single string
        $whereArray = [];

        if (\count($globalSearch) > 0) {
            $whereArray[] = '(' . implode(' OR ', $globalSearch) . ')';
        }

        if (\count($columnSearch) > 0) {
            $whereArray = array_merge($whereArray, $columnSearch);
        }

        if (\count($whereArray) > 0) {
            return 'WHERE ' . implode(' AND ', $whereArray);
        } else {
            return '';
        }
    }

    /**
     * Perform the SQL queries needed for a server-side processing request.
     *
     * @return array{
     *     draw: int,
     *     recordsTotal: int,
     *     recordsFiltered: int,
     *     data: array<array<string, string|null>>,
     * } Server-side processing response array
     *
     * @throws GeenLijstException
     */
    public function get(): array
    {
        // Build the SQL query string from the request
        $select = $this->select();
        $limit = $this->limit();
        $order = $this->order();
        $where = $this->filter();

        $basis_query = "FROM nummers n\n";
        if ($this->lijst !== null) {
            $basis_query .= <<<SQL
            INNER JOIN lijsten_nummers l ON
                n.id = l.nummer_id
                AND l.lijst_id = {$this->lijst->id}
            SQL;
        }
        // Resultaten
        $query = \implode(' ', [$select, $basis_query, $where, $order, $limit]);
        // Aantal resultaten
        $count_query = <<<SQL
        SELECT COUNT(n.id)
        {$basis_query} {$where}
        SQL;
        // Total data set length
        $total_length_query = <<<SQL
        SELECT COUNT(n.id) 
        {$basis_query}
        SQL;
        $data = $this->db->query($query);

        // Data set length after filtering
        $recordsFiltered = (int)$this->db->selectSingle($count_query);

        // Total data set length
        $recordsTotal = (int)$this->db->selectSingle($total_length_query);

        // Output
        return [
            'draw' => $this->draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $this->data_output($data),
        ];
    }
}
