<?php
 
/*
 * DataTables example server-side processing script.
 *
 * Please note that this script is intentionally extremely simply to show how
 * server-side processing can be implemented, and probably shouldn't be used as
 * the basis for a large complex system. It is suitable for simple use cases as
 * for learning.
 *
 * See http://datatables.net/usage/server-side for full details on the server-
 * side processing requirements of DataTables.
 *
 * @license MIT - http://datatables.net/license_mit
 */
 
require_once __DIR__.'/include/include.php';

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * Easy set variables
 */
 
// DB table to use
$table = 'muzieklijst_nummers';
 
// Table's primary key
$primaryKey = 'id';
 
// Array of database columns which should be read and sent back to DataTables.
// The `db` parameter represents the column name in the database, while the `dt`
// parameter represents the DataTables column identifier. In this case simple
// indexes
$columns = array(

	array( 'db' => 'id',     'dt' => 0 ),
    array( 'db' => 'titel', 'dt' => 1 ),
    array( 'db' => 'artiest',  'dt' => 2 ),
    array( 'db' => 'jaar',   'dt' => 3 )
    
);
 
// SQL server connection information
if ( is_dev() ) {
	$sql_details = array(
		'user' => 'remy',
		'pass' => 'TxpJOe5MV0y4vP6t',
		'db'   => 'remy_muzieklijsten',
		'host' => 'localhost'
	);
} else {
	$sql_details = array(
		'user' => 'w3omrpg',
		'pass' => 'H@l*lOah',
		'db'   => 'rtvgelderland',
		'host' => 'localhost'
	);
}
 
 
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * If you just want to use the basic configuration for DataTables with PHP
 * server-side, there is no need to edit below this line.
 */

 
require( 'ssp.class.php' );
 
echo json_encode(
    SSP::simple( $_GET, $sql_details, $table, $primaryKey, $columns )
);