<?php

require_once __DIR__.'/include/include.php';

if ( is_dev() ) {
	error_reporting(E_ALL & ~E_NOTICE);
}

login();

$lijst_id = (int)$_GET['lijst'];
if ( $lijst_id === 0 ) {
	$lijst = null;
	$lijst_query = '';
	$nummers = [];
} else {
	$lijst = new Muzieklijst($lijst_id);
	$lijst_query = sprintf('?lijst=%d', $lijst->get_id());
	$nummers = $lijst->get_nummers();
}

$andere_lijsten = get_muzieklijsten();

$select_lijst_html = '';
foreach ( $andere_lijsten as $andere_lijst ) {
	if ( $andere_lijst->equals($lijst) ) {
		$selected = ' selected';
	} else {
		$selected = '';
	}
	$select_lijst_html .= sprintf(
		'<option value="%d"%s>%s</option>',
		$andere_lijst->get_id(),
		$selected,
		$andere_lijst->get_naam()
	);
}

$nummer_ids = [];
foreach ( $nummers as $nummer ) {
	$nummer_ids[] = (string)$nummer->get_id();
}
$rows_selected = json_encode($nummer_ids);

$totaal_aantal_nummers = count(get_nummers());
	
?>

<!DOCTYPE html>
<html lang="nl">
<head>
	<meta charset="utf-8">
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/r/dt/dt-1.10.9/datatables.min.css">

	
	<script src="js/jquery-1.11.2.min.js"></script>
	<script src="js/bootstrap.min.js"></script>

	
	<script type="text/javascript" src="https://cdn.datatables.net/r/dt/dt-1.10.9/datatables.min.js"></script>

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
	
	<style type='text/css'>
		body {
			padding-top: 70px;
		}
		table.dataTable.select tbody tr, table.dataTable thead th:first-child {
			cursor: pointer;
		}
		#example_length select {
			width: 80px;
			display: inline;
		}
		#example_filter input {
			width: 200px;
			display: inline;
		}
		.modal-top {
			border-radius: 6px 6px 0px 0px;
			-moz-border-radius: 6px 6px 0px 0px;
			-webkit-border-radius: 6px 6px 0px 0px;
			background-clip: padding-box;
   			background-color: #fff;
			box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
		}
		.modal-end {
			border-radius: 0px 0px 6px 6px;
			-moz-border-radius: 0px 0px 6px 6px;
			-webkit-border-radius: 0px 0px 6px 6px;
			background-clip: padding-box;
   			background-color: #fff;
			box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
		}
		.modal-content {
			box-shadow: none;
			border: none;
			border-radius: 0px;
		}
	</style>
</head>
<body>

<form id="frm-example" action="/nosuchpage" method="POST">

<nav class="navbar navbar-default navbar-fixed-top">
	<div class="container-fluid" style="padding-top: 10px;">
			<table width="100%">
			<tr>
				<td><strong>Omroep GLD - Muzieklijsten</strong></td>
				<td>			
				<select name="lijst" class="form-control" onchange="location.href='?lijst=' + this.value">
				<option value="">-- Selecteer een muzieklijst --
				<?php echo $select_lijst_html; ?>
				</select>
				</td>
				<td align="right"><a href="beheer.php" data-toggle="modal" data-target="#nieuw" data-backdrop="static" data-keyboard="false" class="btn btn-primary" role="button">Nieuw</a></td>
				<td align="right"><a href="beheer.php<?php echo $lijst_query; ?>" data-toggle="modal" data-target="#beheer" data-backdrop="static" data-keyboard="false" class="btn btn-primary" role="button">Beheer</a></td>
				<td align="right"><a href="iframe.php<?php echo $lijst_query; ?>" data-toggle="modal" data-target="#popup" data-backdrop="static" data-keyboard="false" class="btn btn-primary<?php if ( $lijst === null ) { echo ' disabled'; } ?>" role="button">Resultaten</a></td>
				<td align="right"><button class="btn btn-primary<?php if ( $lijst === null ) { echo ' disabled'; } ?>"<?php if ( $lijst === null ) { echo ' disabled="disabled"'; } ?>>Opslaan</button></td>
			</tr>
			</table>
	</div>
</nav>

<div class="modal fade" id="popup" role="dialog">
	<div class="modal-dialog" style="width: 90%;">
		<div class="modal-header modal-top">
			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
			<h4 class="modal-title">Resultaten van de lijst "<?php echo $lijst->get_naam(); ?>"</h4>
		</div>
		<div class="modal-content"></div>
		<div class="modal-footer modal-end">
			<button type="button" class="btn btn-default" data-dismiss="modal">Sluiten</button>
		</div>
	</div>
</div>

<div class="modal fade" id="beheer" role="dialog">
	<div class="modal-dialog" style="width: 90%;">
		<div class="modal-header modal-top">
			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
			<h4 class="modal-title">Beheer van de lijst "<?php echo $lijst->get_naam(); ?>"</h4>
		</div>
		<div class="modal-content"></div>
		<div class="modal-footer modal-end">
			<button type="button" class="btn btn-default" data-dismiss="modal">Sluiten</button>
		</div>
	</div>
</div>

<div class="modal fade" id="nieuw" role="dialog">
	<div class="modal-dialog" style="width: 90%;">
		<div class="modal-header modal-top">
			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
			<h4 class="modal-title">Nieuwe muzieklijst</h4>
		</div>
		<div class="modal-content"></div>
		<div class="modal-footer modal-end">
			<button type="button" class="btn btn-default" data-dismiss="modal">Sluiten</button>
		</div>
	</div>
</div>

<div class="container-fluid">
	<div class="row">
	
		<div class="col-sm-6">
		<h4>Beschikbaar (<?php echo $totaal_aantal_nummers; ?>)</h4>
		<hr style="border: 1px solid #333;">
			<table id="example" class="display select" cellspacing="0" width="100%">
			   <thead>
			      <tr>
			          <th style="text-align: center;"><input name="select_all" value="1" type="checkbox" style="visibility:hidden;"></th>
			         <th>Titel</th>
			         <th>Artiest</th>
			         <th>Jaar</th>
			      </tr>
			   </thead>
			</table>
		</div>

		<div class="col-sm-6">
		<div id="result"></div>
		</div>


	</div>

</div>

</form>

<script type='text/javascript'>//<![CDATA[

//
// Updates "Select all" control in a data table
//
function updateDataTableSelectAllCtrl(table){
   var $table             = table.table().node();
   var $chkbox_all        = $('tbody input[type="checkbox"]', $table);
   var $chkbox_checked    = $('tbody input[type="checkbox"]:checked', $table);
   var chkbox_select_all  = $('thead input[name="select_all"]', $table).get(0);

   // If none of the checkboxes are checked
   if($chkbox_checked.length === 0){
      chkbox_select_all.checked = false;
      if('indeterminate' in chkbox_select_all){
         chkbox_select_all.indeterminate = false;
      }

   // If all of the checkboxes are checked
   } else if ($chkbox_checked.length === $chkbox_all.length){
      chkbox_select_all.checked = true;
      if('indeterminate' in chkbox_select_all){
         chkbox_select_all.indeterminate = false;
      }

   // If some of the checkboxes are checked
   } else {
      chkbox_select_all.checked = true;
      if('indeterminate' in chkbox_select_all){
         chkbox_select_all.indeterminate = true;
      }
   }
}

$(document).ready(function (){

   // Array holding selected row IDs
   var rows_selected = <?php echo $rows_selected; ?>;
   var table = $('#example').DataTable({
      'processing': true,
      'serverSide': true,	
      'ajax': 'server_processing.php',
      'columnDefs': [{
         'targets': 0,
         'searchable':false,
         'orderable':false,
         'className': 'dt-body-center',
         'render': function (data, type, full, meta){
             return '<input type="checkbox">';
         }
      }],
      'order': [1, 'asc'],
      'rowCallback': function(row, data, dataIndex){
         // Get row ID
         var rowId = data[0];
		
         // If row ID is in the list of selected row IDs
         if($.inArray(rowId, rows_selected) !== -1){
            $(row).find('input[type="checkbox"]').prop('checked', true);
            $(row).addClass('selected');
         }
      },
	  'language': {
            "lengthMenu": "_MENU_ nummers per pagina",
            "zeroRecords": "Geen nummers gevonden",
            "info": "Pagina _PAGE_ van _PAGES_",
            "infoEmpty": "Geen nummers gevonden",
            "infoFiltered": "(gefilterd van _MAX_ totaal)",
			"search":         "Zoeken:",
			"paginate": {
       		 	"first":      "Eerste",
				"last":       "Laatste",
				"next":       "Volgende",
				"previous":   "Vorige"
			},
        }
   });

   // Handle click on checkbox
   $('#example tbody').on('click', 'input[type="checkbox"]', function(e){
      var $row = $(this).closest('tr');

      // Get row data
      var data = table.row($row).data();

      // Get row ID
      var rowId = data[0];

      // Determine whether row ID is in the list of selected row IDs 
      var index = $.inArray(rowId, rows_selected);

      // If checkbox is checked and row ID is not in list of selected row IDs
      if(this.checked && index === -1){
         rows_selected.push(rowId);

      // Otherwise, if checkbox is not checked and row ID is in list of selected row IDs
      } else if (!this.checked && index !== -1){
         rows_selected.splice(index, 1);
      }

      if(this.checked){
         $row.addClass('selected');
      } else {
         $row.removeClass('selected');
      }

      // Update state of "Select all" control
      updateDataTableSelectAllCtrl(table);

      // Prevent click event from propagating to parent
      e.stopPropagation();
   });

   // Handle click on table cells with checkboxes
   $('#example').on('click', 'tbody td, thead th:first-child', function(e){
      $(this).parent().find('input[type="checkbox"]').trigger('click');
   });

   // Handle click on "Select all" control
   $('#example thead input[name="select_all"]').on('click', function(e){
      if(this.checked){
         $('#example tbody input[type="checkbox"]:not(:checked)').trigger('click');
      } else {
         $('#example tbody input[type="checkbox"]:checked').trigger('click');
      }

      // Prevent click event from propagating to parent
      e.stopPropagation();
   });

   // Handle table draw event
   table.on('draw', function(){
      // Update state of "Select all" control
      updateDataTableSelectAllCtrl(table);
   });
    
   // Handle form submission event 
   $('#frm-example').on('submit', function(e){
      var form = this;

      // Iterate over all selected checkboxes
      $.each(rows_selected, function(index, rowId){
         // Create a hidden element 
         $(form).append(
             $('<input>')
                .attr('type', 'hidden')
                .attr('name', 'id[]')
                .val(rowId)
         );
      });

      // FOR DEMONSTRATION ONLY     
      
      // Output form data to a console     
      //$('#example-console').text($(form).serialize());
     //console.log("Form submission", $(form).serialize());
       
	   
		$.ajax({
		type: 'POST',
		url: 'update_list.php',
		data: $(form).serialize(),
		success: function( data ) {
			$("#result").load("selected.php<?php echo $lijst_query; ?>");
		}
		});
	   
	   
	   
      // Remove added elements
      $('input[name="id\[\]"]', form).remove();
       
      // Prevent actual form submission
      e.preventDefault();
   });
   
   <?php
   if ( $lijst !== null ) {
   ?>
   $("#result").load("selected.php<?php echo $lijst_query; ?>");
   <?php
   }
   ?>
   
   $("#example_length select").addClass("form-control");
   $("#example_filter input").addClass("form-control");

   
   
});
//]]> 






	
</script>




</body>

</html>
