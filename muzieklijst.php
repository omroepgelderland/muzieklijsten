<?php

require_once __DIR__.'/include/include.php';

if ( is_dev() ) {
	error_reporting(E_ALL & ~E_NOTICE);
}

$link = Muzieklijsten_Database::getDB();
session_start();

if ( !array_key_exists('lijst', $_GET) ) {
	throw new Muzieklijsten_Exception('Geef een lijst mee in de URL');
}

$lijst = (int)$_GET['lijst'];
$muzieklijst = new Muzieklijst($lijst);

$sql = "SELECT recaptcha FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$captcha = $result[0];

if ($captcha == 1) {
	$recaptcha = new \ReCaptcha\ReCaptcha("6LdH7wsTAAAAADDnMKZ4g-c6f125Ftr0JQR-BDQp");
	$_SESSION['captcha'] = 1;
}

$sql = "SELECT actief FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$actief = $result[0];

$sql = "SELECT minkeuzes FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$minkeuzes = $result[0];

$sql = "SELECT maxkeuzes FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$maxkeuzes = $result[0];

$sql = "SELECT stemmen_per_ip FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$stemmen_per_ip = $result[0];

$sql = "SELECT COUNT(DISTINCT t.id) FROM muzieklijst_stemmen s, muzieklijst_stemmers t WHERE s.stemmer_id = t.id AND s.lijst_id = ".$lijst." AND t.ip = '".$_SERVER["REMOTE_ADDR"]."'";
$result = mysqli_fetch_array($link->query($sql));
$stemmen_huidig_ip = $result[0];

$sql = "SELECT artiest_eenmalig FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$artiest_eenmalig = $result[0];

$sql = "SELECT veld_telefoonnummer FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$veld_telefoonnummer = $result[0];
$_SESSION["veld_telefoonnummer"] = $veld_telefoonnummer;

$sql = "SELECT veld_email FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$veld_email = $result[0];
$_SESSION["veld_email"] = $veld_email;

$sql = "SELECT veld_woonplaats FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$veld_woonplaats = $result[0];
$_SESSION["veld_woonplaats"] = $veld_woonplaats;

$sql = "SELECT veld_adres FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$veld_adres = $result[0];
$_SESSION["veld_adres"] = $veld_adres;

$sql = "SELECT veld_uitzenddatum FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$veld_uitzenddatum = $result[0];
$_SESSION["veld_uitzenddatum"] = $veld_uitzenddatum;

$sql = "SELECT veld_vrijekeus FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$veld_vrijekeus = $result[0];
$_SESSION["veld_vrijekeus"] = $veld_vrijekeus;

?>

<!DOCTYPE html>
<html lang="nl">
<head>
	<meta charset="utf-8">
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
	
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/r/dt/jq-2.1.4,dt-1.10.8/datatables.min.css">
	<script type='text/javascript' src='//code.jquery.com/jquery-1.11.0.js'></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
	<script type='text/javascript' src="https://cdn.datatables.net/r/dt/jq-2.1.4,dt-1.10.8/datatables.min.js"></script>
	<script src="js/moment.js"></script>
	<script src="js/nl.js"></script>
	<script src="js/bootstrap-datetimepicker.js"></script>
	
	<style type='text/css'>
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
		.remark {
			padding-right: 0px !important;
		}
		#postcode {
			width: 10em;
		}
	</style>
</head>
<body>
<!-- FB share knop script -->
<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "https://connect.facebook.net/nl_NL/sdk.js#xfbml=1&version=v2.10&appId=1269120393132176";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
<!-- Einde FB share knop script -->
<script language="JavaScript">
function validate() {
<?php
if ($minkeuzes != 0) {
?>
	if (form.nrselected.value < <?php echo $minkeuzes; ?>) {
		alert("U moet mimimaal <?php echo $minkeuzes; ?> nummers selecteren.");
		return (false);
	}
<?php
}
?>
	if (form.naam.value == "") {
		alert("Vul het ontbrekende veld in a.u.b.");
		form.naam.focus();
		return (false);
	}
	
<?php
if ($veld_woonplaats == 1 || $veld_adres == 1) {
?>
	if (form.woonplaats.value == "") {
		alert("Vul het ontbrekende veld in a.u.b.");
		form.woonplaats.focus();
		return (false);
	}
	
<?php
}
if ($veld_adres == 1) {
?>
	if (form.adres.value == "") {
		alert("Vul het ontbrekende veld in a.u.b.");
		form.adres.focus();
		return (false);
	}
<?php
}
if ($veld_telefoonnummer == 1) {
?>
	
	if (form.telefoonnummer.value == "") {
		alert("Vul het ontbrekende veld in a.u.b.");
		form.telefoonnummer.focus();
		return (false);
	}
	
<?php
}
if ($veld_email == 1) {
?>
	if (form.veld_email.value == "") {
		alert("Vul het ontbrekende veld in a.u.b.");
		form.veld_email.focus();
		return (false);
	}
<?php
}

if ($veld_uitzenddatum == 1) {
?>
	if (form.veld_uitzenddatum.value == "") {
		alert("Vul het ontbrekende veld in a.u.b.");
		form.veld_uitzenddatum.focus();
		return (false);
	}
<?php
}

?>
	var geldig = true;
	$('input[required]').each(function() {
		if ( $(this).val() == '' ) {
			var leeg_feedback = $(this).data('leeg-feedback');
			alert(leeg_feedback);
			return geldig = false;
		}
	});
	if ( !geldig ) {
		return (false);
	}
<?php

if ($captcha == 1) {
?>
	if (document.getElementById('g-recaptcha-response').value  == "") {
		alert("Plaats een vinkje a.u.b.");
		return (false);
	}
<?php
}
?>
	document.getElementById('session').value = '<?php echo session_id(); ?>';
	
	 
	var hoogte = $('#example_wrapper').outerHeight();
	$('#example_wrapper').hide();
	$('#table_placeholder').height(hoogte);
	$('#contactform').hide();
//	$('.g-recaptcha').hide();
	
	//document.getElementById('contactform').style.display = 'none';
	
	//$('input:checkbox').removeAttr('checked');
	//$('tr').removeClass('selected');
	//var rows_selected = '';
	

           

	
	return (true);

}


</script>


  
<form id="frm-example" name="form" method="POST" class="form-horizontal" role="form">


<div class="container-fluid">




	<div class="row">
	
		<div class="col-sm-12">
		  <div id="table_placeholder"></div>
			<table id="example" class="display select" cellspacing="0" width="100%">
			   <thead>
			      <tr>
			          <th></th>
			         <th>Titel</th>
			         <th>Artiest</th>
			      </tr>
			   </thead>
			</table>
		</div>
		
		<div id="result"></div>
		
		<p>&nbsp;</p>
		
		<div class="col-sm-12" id="contactform">
		
			<div class="form-group">
				<label class="control-label col-sm-2" for="naam">Naam</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="naam" name="naam">
				</div>
			</div>

<?php
if ($veld_adres == 1) {
?>
			<div class="form-group">
				<label class="control-label col-sm-2" for="adres">Adres</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="adres" name="adres">
				</div>
			</div>
			<div class="form-group">
				<label class="control-label col-sm-2" for="postcode">Postcode</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="postcode" name="postcode">
				</div>
			</div>
<?php
}
if ($veld_woonplaats == 1 || $veld_adres == 1 ) {
?>
			<div class="form-group">
				<label class="control-label col-sm-2" for="woonplaats">Woonplaats</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="woonplaats" name="woonplaats">
				</div>
			</div>
			
<?php
}
if ($veld_telefoonnummer == 1) {
?>
			
			<div class="form-group">
				<label class="control-label col-sm-2" for="telefoonnummer">Telefoonnummer</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="telefoonnummer" name="telefoonnummer">
				</div>
			</div>	
<?php
}
if ($veld_email == 1) {
?>
			<div class="form-group">
				<label class="control-label col-sm-2" for="veld_email">E-mailadres</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="veld_email" name="veld_email">
				</div>
			</div>
<?php
}

if ($veld_uitzenddatum == 1) {
?>
			<div class="form-group">
				<label class="control-label col-sm-2" for="veld_uitzenddatum">Uitzenddatum</label>
				<div class="col-sm-10">
					<div class="input-group date" id="datetimepicker">
						<input type="text" name="veld_uitzenddatum" id="veld_uitzenddatum" class="form-control" placeholder="selecteer een datum">
						<span class="input-group-addon">
							<span class="glyphicon glyphicon-calendar"></span>
						</span>
			</div>
				</div>
			</div>
<?php
}

if ($veld_vrijekeus == 1) {
?>
			<div class="form-group">
				<label class="control-label col-sm-2" for="veld_vrijekeus">Vrije keuze</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="veld_vrijekeus" name="veld_vrijekeus" placeholder="Vul hier uw eigen favoriet in">
				</div>
			</div>
<?php
}

foreach ( $muzieklijst->get_extra_velden() as $extra_veld ) {
	echo $extra_veld->get_formulier_html();
}

if ($captcha == 1) {
?>
			<div class="form-group">
				<label class="control-label col-sm-2" for="code"></label>
				<div class="col-sm-10">
					<div class="g-recaptcha" data-sitekey="6LdH7wsTAAAAADp6WEZlXlc91uTjFKXd1DXHnGXB"></div>
					<script type="text/javascript" src="https://www.google.com/recaptcha/api.js?hl=nl"></script>
				</div>
			</div>
<?php
}

if ($actief == 0) {
?>
			<div class="form-group">
				<label class="control-label col-sm-2" for="submit"></label>
				<div class="col-sm-10">
					Er kan niet meer worden gestemd.
			</div>
<?php
} else if ( ($stemmen_per_ip == NULL || $stemmen_per_ip == "")  || ($stemmen_huidig_ip < $stemmen_per_ip) ) {

?>

			<div style="margin: 20px;">
				<small>Je moet minimaal 16 jaar zijn om deel te nemen aan deze dienst. Wilt u meer informatie over hoe Omroep Gelderland omgaat met je gegevens, lees dan ons <a href="https://www.gld.nl/privacyverklaring" target="_parent">privacystatement</a></small>	
			</div>
	
			<div class="form-group">
				<label class="control-label col-sm-2" for="submit"></label>
				<div class="col-sm-2">
					<button type="submit" class="btn btn-default" id="submit" name="submit" onclick="return validate(); ">Versturen</button>
				</div>
			</div>
			
<?php
} else {
?>

			<div class="form-group">
				<label class="control-label col-sm-2" for="submit"></label>
				<div class="col-sm-10">
					Er kon maximaal <?php echo $stemmen_per_ip; ?> keer worden gestemd vanaf dit IP adres.
			</div>

<?php
}
?>
		</div>
		
	</div>

	

</div>

<input type="hidden" name="session" id="session">
<input type="hidden" name="lijst" value="<?php echo $lijst; ?>">
</form>

<script type='text/javascript'>//<![CDATA[

$(document).ready(function (){
   // Array holding selected row IDs
   var rows_selected = [];
   var rows_selected2 = [];
   var table = $('#example').DataTable({
      'processing': true,
      'serverSide': true,	
      'ajax': {
	  	 'url': 'server_processing.php',
		 'data': function (d) {
		 	d.lijst = '<?php echo $lijst; ?>';
		 }
	  },
	  'bLengthChange': false,
	  'iDisplayLength': 50,
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

<?php
if ($artiest_eenmalig == 1) {
?>
	  
      // Get row ID
      var rowId2 = data[2];

      // Determine whether row ID is in the list of selected row IDs 
      var index2 = $.inArray(rowId2, rows_selected2);
      // If checkbox is checked and row ID is not in list of selected row IDs
      if(this.checked){
         rows_selected2.push(rowId2);

      // Otherwise, if checkbox is not checked and row ID is in list of selected row IDs
      } else if (!this.checked){
         rows_selected2.splice(index2, 1);
      }
    
		
		var unique = function(a) {
			var counts = [];
			for(var i = 0; i <= a.length; i++) {
				if(counts[a[i]] === undefined) {
					counts[a[i]] = 1;
				} else {
					return true;
				}
			}
			return false;
		}

		if (unique(rows_selected2) == true) {
			$(this).prop( "checked", false );
			rows_selected2.splice(index, 1);
			rows_selected.splice(index, 1);
			alert("Deze artiest is al gekozen");
		}

<?php
}
?>
	  
		if (rows_selected.length > <?php echo $maxkeuzes; ?>) {
			$(this).prop( "checked", false );
			rows_selected.splice(index, 1);
			alert("U kunt maximaal <?php echo $maxkeuzes; ?> nummers selecteren.");
			
	  }
	  
	  
	  
      if(this.checked){
         $row.addClass('selected');
      } else {
         $row.removeClass('selected');
      }

	//  $("#session").val("");
	//  $("#contactform").show();
	  
	  $('#frm-example').submit();

      // Prevent click event from propagating to parent
      e.stopPropagation();
   });

   // Handle click on table cells with checkboxes
   $('#example').on('click', 'tbody td, thead th:first-child', function(e){
      $(this).parent().find('input[type="checkbox"]').trigger('click');
   });


   // Handle form submission event 
   $('#frm-example').on('submit', function(e){
	   console.log('submit');
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
		 $('#nrselected').remove();
		 $(form).append(
             $('<input>')
                .attr('type', 'hidden')
                .attr('name', 'nrselected')
				.attr('id', 'nrselected')
                .val(rows_selected.length)
         );
	   
		$.ajax({
		type: 'POST',
		url: 'show_data.php',	// TODO
		data: $(form).serialize(),
		success: function( data ) {
			$('#result').html(data);
		}
		});
	   
	   
	   
      // Remove added elements
      $('input[name="id\[\]"]', form).remove();
       
      // Prevent actual form submission
      e.preventDefault();
   });
   

   
});
//]]> 

<?php
if ($veld_uitzenddatum == 1) {
?>
$(function () {
        $("#datetimepicker").datetimepicker({
			locale: 'nl',
			format: 'DD-MM-YYYY'
		});
});
<?php
}
?>
</script>








</body>

</html>
