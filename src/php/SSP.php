<?php
/**
 * Helper functions for building a DataTables server-side processing SQL query
 *
 * The static functions in this class are just helper functions to help build
 * the SQL used in the DataTables demo server-side processing scripts. These
 * functions obviously do not represent all that can be done with server-side
 * processing, they are intentionally simple to show how it works. More complex
 * server-side processing operations will likely require a custom script.
 *
 * See http://datatables.net/usage/server-side for full details on the server-
 * side processing requirements of DataTables.
 *
 * @license MIT - http://datatables.net/license_mit
 */

namespace muzieklijsten;

use mysqli_result;

class SSP {

	private \stdClass $request;
	private array $kolommen;
	private Lijst $lijst;
	private ?bool $is_vrijekeuze;

	public function __construct(
		\stdClass $request,
		array $kolommen
	) {
		$this->request = $request;
		$this->kolommen = $kolommen;
	}

	/**
	 * Create the data output array for the DataTables rows
	 *
	 *  @param  \mysqli_result $data    Data from the SQL get
	 *  @return array          Formatted data in a row based format
	 */
	protected function data_output ( \mysqli_result $data ): array {
		$out = [];

		foreach( $data as $entry ) {
			$row = [];
			foreach ( $this->kolommen as $kolom ) {
				$row[$kolom['dt']] = $entry[$kolom['db']];
			}
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Paging
	 *
	 * Construct the LIMIT clause for server-side processing SQL query
	 *
	 */
	protected function limit()
	{
		$start = filter_var($this->request->start, FILTER_VALIDATE_INT);
		$length = filter_var($this->request->length, FILTER_VALIDATE_INT);
		if ( $start !== false && $start !== null && $length !== -1 ) {
			return "LIMIT {$start}, {$length}";
		} else {
			return '';
		}
	}


	/**
	 * Ordering
	 *
	 * Construct the ORDER BY clause for server-side processing SQL query
	 *
	 *  @return string SQL order by clause
	 */
	protected function order() {
		if ( $this->get_lijst()->is_random_volgorde() ) {
			return "ORDER BY RAND({$this->request->random_seed})";
		}
		else if ( isset($this->request->order) && count($this->request->order) > 0 ) {
			$orderBy = [];
			$dtColumns = self::pluck( $this->kolommen, 'dt' );

			foreach ( $this->request->order as $kolom_order ) {
			// for ( $i=0, $ien=count($order_request) ; $i<$ien ; $i++ ) {
				// Convert the column index into the column data property
				$columnIdx = filter_var($kolom_order->column, FILTER_VALIDATE_INT);
				$requestColumn = $this->request->columns[$columnIdx];

				$columnIdx = array_search( $requestColumn->data, $dtColumns );
				$column = $this->kolommen[$columnIdx];

				if ( filter_var($requestColumn->orderable, FILTER_VALIDATE_BOOLEAN) ) {
					$dir = $kolom_order->dir === 'asc' ?
						'ASC' :
						'DESC';

					$orderBy[] = '`'.$column['db'].'` '.$dir;
				}
			}

			if ( count($orderBy) > 0 ) {
				return 'ORDER BY '.implode(', ', $orderBy);
			} else {
				return '';
			}
		} else {
			return '';
		}
	}


	/**
	 * Searching / Filtering
	 *
	 * Construct the WHERE clause for server-side processing SQL query.
	 *
	 * NOTE this does not match the built-in DataTables filtering which does it
	 * word by word on any field. It's possible to do here performance on large
	 * databases would be very poor
	 *
	 *  @return string SQL where clause
	 */
	protected function filter (): string {
		$search_value = DB::escape_string($this->request->search->value);
		$globalSearch = [];
		$columnSearch = [];
		$dtColumns = self::pluck( $this->kolommen, 'dt' );

		if ( $this->is_vrijekeuze() !== null ) {
			$is_vrijekeuze = (int)$this->is_vrijekeuze();
			$columnSearch[] = "is_vrijekeuze = {$is_vrijekeuze}";
		}

		if ( isset($this->request->search) && $search_value != '' ) {
			foreach ( $this->request->columns as $requestColumn ) {
				$columnIdx = array_search($requestColumn->data, $dtColumns);
				$column = $this->kolommen[$columnIdx];

				if ( $requestColumn->searchable ) {
					$globalSearch[] = "`{$column['db']}` LIKE \"%{$search_value}%\"";
				}
			}
		}

		// Individual column filtering
		foreach( $this->request->columns as $requestColumn ) {
			$columnIdx = array_search($requestColumn->data, $dtColumns);
			$column = $this->kolommen[$columnIdx];

			$str = DB::escape_string($requestColumn->search->value);

			if ( $requestColumn->searchable && $str != '' ) {
				$columnSearch[] = "`{$column['db']}` LIKE \"%{$str}%\"";
			}
		}

		// Combine the filters into a single string
		$where = '';

		if ( count( $globalSearch ) > 0 ) {
			$where = '('.implode(' OR ', $globalSearch).')';
		}

		if ( count( $columnSearch ) > 0 ) {
			$where = $where === '' ?
				implode(' AND ', $columnSearch) :
				$where .' AND '. implode(' AND ', $columnSearch);
		}

		if ( $where !== '' ) {
			$where = 'WHERE '.$where;
		}

		return $where;
	}


	/**
	 * Perform the SQL queries needed for an server-side processing requested,
	 * utilising the helper functions of this class, limit(), order() and
	 * filter() among others. The returned array is ready to be encoded as JSON
	 * in response to an SSP request, or can be modified if needed before
	 * sending back to the client.
	 *
	 *  @return array          Server-side processing response array
	 * @throws GeenLijstException
	 */
	public function simple (): array
	{
		$db = DB::getDB();

		// Build the SQL query string from the request
		$limit = $this->limit();
		$order = $this->order();
		$where = $this->filter();

		try {
			$lijst = $this->get_lijst();
			$basis_query = <<<EOT
				FROM nummers n
				INNER JOIN lijsten_nummers l ON
					n.id = l.nummer_id
					AND l.lijst_id = {$lijst->get_id()}
			EOT;
			// Resultaten
			$query = <<<EOT
				SELECT n.id, n.titel, n.artiest, n.jaar 
				{$basis_query} {$where} {$order} {$limit}
			EOT;
			// Aantal resultaten
			$count_query = <<<EOT
				SELECT COUNT(n.id)
				{$basis_query} {$where}
			EOT;
			// Total data set length
			$total_length_query = <<<EOT
				SELECT COUNT(n.id) 
				{$basis_query}
			EOT;
		} catch ( Muzieklijsten_Exception $e ) {
			$select = implode("`, `", self::pluck($this->kolommen, 'db'));
			$basis_query = "FROM nummers";
			// Resultaten
			$query = <<<EOT
				SELECT `{$select}`
				{$basis_query} {$where} {$order} {$limit}
			EOT;
			// Aantal resultaten
			$count_query = <<<EOT
				SELECT COUNT(id)
				{$basis_query} {$where}
			EOT;
			// Total data set length
			$total_length_query = <<<EOT
				SELECT COUNT(id)
				FROM nummers
			EOT;
		}
		$data = DB::query($query);
		
		// Data set length after filtering
		$recordsFiltered = DB::selectSingle($count_query);

		// Total data set length
		$recordsTotal = DB::selectSingle($total_length_query);

		// Output
		return [
			'draw'            => filter_var($this->request->draw, FILTER_VALIDATE_INT),
			'recordsTotal'    => $recordsTotal,
			'recordsFiltered' => $recordsFiltered,
			'data'            => $this->data_output($data)
		];
	}

	/**
	 * Pull a particular property from each assoc. array in a numeric array, 
	 * returning and array of the property values from each item.
	 *
	 *  @param  array  $a    Array to get data from
	 *  @param  string $prop Property to read
	 *  @return array        Array of property values
	 */
	protected static function pluck( array $a, string $prop ): array {
		$out = [];

		for ( $i=0, $len=count($a) ; $i<$len ; $i++ ) {
			$out[] = $a[$i][$prop];
		}

		return $out;
	}

	/**
	 * 
	 * @throws GeenLijstException
	 */
	private function get_lijst(): Lijst {
		$this->lijst ??= Lijst::maak_uit_request($this->request);
		return $this->lijst;
	}

	/**
	 * Geeft aan of vrije keuzenummers mogen voorkomen in het resultaat.
	 * @return bool True is alleen vrije keuzes, False is geen vrije keuzes,
	 * null is geen filter.
	 */
	private function is_vrijekeuze(): bool | null {
		if ( !isset($this->is_vrijekeuze) ) {
			try {
				$this->is_vrijekeuze = $this->request->is_vrijekeuze;
			} catch ( UndefinedPropertyException ) {
				$this->is_vrijekeuze = null;
			}
		}
		return $this->is_vrijekeuze;
	}
}
