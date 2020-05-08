<?php

require_once __DIR__.'/include/include.php';

$link = Muzieklijsten_Database::getDB();

$lijst = (int)$_GET['lijst'];

echo '<h4>Geselecteerd';
if ($lijst) {
	$sql = "SELECT COUNT(id) as aantal FROM muzieklijst_nummers_lijst WHERE lijst_id = ".$lijst;
	$result = mysqli_fetch_array($link->query($sql));
	echo ' ('.$result[0].')';
}
echo '</h4>';
echo '<hr style="border: 1px solid #333;">';

$sql = "SELECT n.titel, n.artiest, n.jaar FROM muzieklijst_nummers n, muzieklijst_nummers_lijst l WHERE n.id = l.nummer_id AND l.lijst_id = ".$lijst." ORDER BY titel";
$result = $link->query($sql);
echo '<table class="table table-striped">
	<thead>
	<tr>
		<th>Titel</th>
		<th>Artiest</th>
		<th>Jaar</th>
	</tr>
	</thead>
	<tbody>';
while ($r = mysqli_fetch_array($result)) {
	echo '<tr>';
	echo '<td>'.utf8_encode($r["titel"]).'</td>';
	echo '<td>'.utf8_encode($r["artiest"]).'</td>';
	echo '<td>'.$r["jaar"].'</td>';
	echo '</tr>';
}
echo '</tbody></table>';
