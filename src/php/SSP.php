<?php

/*
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

	private int $input_type;
	private string $tabel;
	private string $primary_key;
	private array $kolommen;
	private Lijst $lijst;

	public function __construct(
		int $input_type,
		string $tabel,
		string $primary_key,
		array $kolommen
	) {
		$this->input_type = $input_type;
		$this->tabel = $tabel;
		$this->primary_key = $primary_key;
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
		$start = filter_input($this->input_type, 'start', FILTER_VALIDATE_INT);
		$length = filter_input($this->input_type, 'length', FILTER_VALIDATE_INT);
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
		$order_request = filter_input($this->input_type, 'order', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
		$columns_request = filter_input($this->input_type, 'columns', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
		
		if ( isset($order_request) && count($order_request) > 0 ) {
			$orderBy = [];
			$dtColumns = self::pluck( $this->kolommen, 'dt' );

			foreach ( $order_request as $kolom_order ) {
			// for ( $i=0, $ien=count($order_request) ; $i<$ien ; $i++ ) {
				// Convert the column index into the column data property
				$columnIdx = filter_var($kolom_order['column'], FILTER_VALIDATE_INT);
				$requestColumn = $columns_request[$columnIdx];

				$columnIdx = array_search( $requestColumn['data'], $dtColumns );
				$column = $this->kolommen[$columnIdx];

				if ( $requestColumn['orderable'] == 'true' ) {
					$dir = $kolom_order['dir'] === 'asc' ?
						'ASC' :
						'DESC';

					$orderBy[] = '`'.$column['db'].'` '.$dir;
				}
			}

			return 'ORDER BY '.implode(', ', $orderBy);
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
		$search_request = filter_input($this->input_type, 'search', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
		$search_value = DB::escape_string($search_request['value']);
		$columns_request = filter_input($this->input_type, 'columns', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
		$globalSearch = [];
		$columnSearch = [];
		$dtColumns = self::pluck( $this->kolommen, 'dt' );

		if ( isset($search_request) && $search_value != '' ) {
			foreach ( $columns_request as $requestColumn ) {
				$columnIdx = array_search($requestColumn['data'], $dtColumns);
				$column = $this->kolommen[$columnIdx];

				if ( $requestColumn['searchable'] == 'true' ) {
					$globalSearch[] = "`{$column['db']}` LIKE \"%{$search_value}%\"";
				}
			}
		}

		// Individual column filtering
		foreach( $columns_request as $requestColumn ) {
			$columnIdx = array_search($requestColumn['data'], $dtColumns);
			$column = $this->kolommen[$columnIdx];

			$str = DB::escape_string($requestColumn['search']['value']);

			if ( $requestColumn['searchable'] == 'true' && $str != '' ) {
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
				FROM `{$this->tabel}` n
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
			$basis_query = "FROM `{$this->tabel}`";
			// Resultaten
			$query = <<<EOT
				SELECT `{$select}`
				{$basis_query} {$where} {$order} {$limit}
			EOT;
			// Aantal resultaten
			$count_query = <<<EOT
				SELECT COUNT(`{$this->primary_key}`)
				{$basis_query} {$where}
			EOT;
			// Total data set length
			$total_length_query = <<<EOT
				SELECT COUNT(`{$this->primary_key}`)
				FROM `$this->tabel`
			EOT;
		}
		$data = DB::query($query);
		
		// Data set length after filtering
		$recordsFiltered = DB::selectSingle($count_query);

		// Total data set length
		$recordsTotal = DB::selectSingle($total_length_query);

		// Output
		return [
			'draw'            => filter_input($this->input_type, 'draw', FILTER_VALIDATE_INT),
			'recordsTotal'    => $recordsTotal,
			'recordsFiltered' => $recordsFiltered,
			'data'            => $this->data_output($data)
		];
	}


	// /**
	//  * The difference between this method and the `simple` one, is that you can
	//  * apply additional `where` conditions to the SQL queries. These can be in
	//  * one of two forms:
	//  *
	//  * * 'Result condition' - This is applied to the result set, but not the
	//  *   overall paging information query - i.e. it will not effect the number
	//  *   of records that a user sees they can have access to. This should be
	//  *   used when you want apply a filtering condition that the user has sent.
	//  * * 'All condition' - This is applied to all queries that are made and
	//  *   reduces the number of records that the user can access. This should be
	//  *   used in conditions where you don't want the user to ever have access to
	//  *   particular records (for example, restricting by a login id).
	//  *
	//  *  @param  array $request Data sent to server by DataTables
	//  *  @param  array|PDO $conn PDO connection resource or connection parameters array
	//  *  @param  string $table SQL table to query
	//  *  @param  string $primaryKey Primary key of the table
	//  *  @param  array $columns Column information array
	//  *  @param  string $whereResult WHERE condition to apply to the result set
	//  *  @param  string $whereAll WHERE condition to apply to all queries
	//  *  @return array          Server-side processing response array
	//  */
	// static function complex ( $request, $conn, $table, $primaryKey, $columns, $whereResult=null, $whereAll=null )
	// {
	// 	$bindings = [];
	// 	$db = self::db( $conn );
	// 	$localWhereResult = [];
	// 	$localWhereAll = [];
	// 	$whereAllSql = '';

	// 	// Build the SQL query string from the request
	// 	$limit = self::limit( $request, $columns );
	// 	$order = self::order( $request, $columns );
	// 	$where = self::filter( $request, $columns, $bindings );

	// 	$whereResult = self::_flatten( $whereResult );
	// 	$whereAll = self::_flatten( $whereAll );

	// 	if ( $whereResult ) {
	// 		$where = $where ?
	// 			$where .' AND '.$whereResult :
	// 			'AND '.$whereResult;
	// 	}

	// 	if ( $whereAll ) {
	// 		$where = $where ?
	// 			$where .' AND '.$whereAll :
	// 			'AND '.$whereAll;

	// 		$whereAllSql = 'AND '.$whereAll;
	// 	}

	// 	$lijst = (int)$_GET['lijst'];
	// 	if ( $lijst != 0 ) {
	// 	// Main query to actually get the data
	// 	$data = self::sql_exec( $db, $bindings,
	// 		"SELECT SQL_CALC_FOUND_ROWS n.id, n.titel, n.artiest, n.jaar 
	// 		 FROM lijsten_nummers l, nummers n 
	// 		 WHERE n.id = l.nummer_id AND l.lijst_id = ".$lijst." 
	// 		 $where
	// 		 $order
	// 		 $limit"
	// 	);
	// 	} else {
	// 	// Main query to actually get the data
	// 	$data = self::sql_exec( $db, $bindings,
	// 		"SELECT SQL_CALC_FOUND_ROWS `".implode("`, `", self::pluck($columns, 'db'))."`
	// 		 FROM `$table`
	// 		 WHERE 1 
	// 		 $where
	// 		 $order
	// 		 $limit"
	// 	);
	// 	}

	// 	// Data set length after filtering
	// 	$resFilterLength = self::sql_exec( $db,
	// 		"SELECT FOUND_ROWS()"
	// 	);
	// 	$recordsFiltered = $resFilterLength[0][0];

	// 	if ( $lijst != 0 ) {
	// 	// Total data set length
	// 	$resTotalLength = self::sql_exec( $db, $bindings,
	// 		"SELECT COUNT(*) 
	// 		 FROM lijsten_nummers l, nummers n 
	// 		 WHERE n.id = l.nummer_id AND l.lijst_id = ".$lijst 
	// 	);
	// 	$recordsTotal = $resTotalLength[0][0];
		
	// 	} else {
	// 	// Total data set length
	// 	$resTotalLength = self::sql_exec( $db, $bindings,
	// 		"SELECT COUNT(`{$primaryKey}`)
	// 		 FROM   `$table` ".
	// 		$whereAllSql
	// 	);
	// 	$recordsTotal = $resTotalLength[0][0];
	// 	}
		
	// 	/*
	// 	 * Output
	// 	 */
	// 	return [
	// 		"draw"            => intval( $request['draw'] ),
	// 		"recordsTotal"    => intval( $recordsTotal ),
	// 		"recordsFiltered" => intval( $recordsFiltered ),
	// 		"data"            => self::data_output( $columns, $data )
	// 	];
	// }

	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
	 * Internal methods
	 */

	// /**
	//  * Throw a fatal error.
	//  *
	//  * This writes out an error message in a JSON string which DataTables will
	//  * see and show to the user in the browser.
	//  *
	//  * @param  string $msg Message to send to the client
	//  */
	// static function fatal ( $msg )
	// {
	// 	echo json_encode( [ 
	// 		"error" => $msg
	// 	] );

	// 	exit(0);
	// }

	// /**
	//  * Create a PDO binding key which can be used for escaping variables safely
	//  * when executing a query with sql_exec()
	//  *
	//  * @param  array &$a    Array of bindings
	//  * @param  *      $val  Value to bind
	//  * @param  int    $type PDO field type
	//  * @return string       Bound key to be used in the SQL where this parameter
	//  *   would be used.
	//  */
	// static function bind ( &$a, $val, $type )
	// {
	// 	$key = ':binding_'.count( $a );

	// 	$a[] = [
	// 		'key' => $key,
	// 		'val' => $val,
	// 		'type' => $type
	// 	];

	// 	return $key;
	// }


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


	// /**
	//  * Return a string from an array or a string
	//  *
	//  * @param  array|string $a Array to join
	//  * @param  string $join Glue for the concatenation
	//  * @return string Joined string
	//  */
	// static function _flatten ( $a, $join = ' AND ' )
	// {
	// 	if ( ! $a ) {
	// 		return '';
	// 	}
	// 	else if ( $a && is_array($a) ) {
	// 		return implode( $join, $a );
	// 	}
	// 	return $a;
	// }

	/**
	 * 
	 * @throws GeenLijstException
	 */
	private function get_lijst(): Lijst {
		$this->lijst ??= Lijst::maak_uit_request($this->input_type);
		return $this->lijst;
	}
}
