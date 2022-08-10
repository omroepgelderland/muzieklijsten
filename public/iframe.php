<?php

namespace muzieklijsten;

require_once __DIR__.'/../vendor/autoload.php';

?>
<!DOCTYPE html>
<html lang="nl-NL">
<head>
    <title>Muzieklijsten iframe</title>
    <link rel="icon" type="image/png" href="afbeeldingen/favicon-192p.png" sizes="192x192">
    <link rel="icon" type="image/png" href="afbeeldingen/favicon-16p.png" sizes="16x16">
    <link rel="icon" type="image/png" href="afbeeldingen/favicon-32p.png" sizes="32x32">
    <link rel="icon" type="image/png" href="afbeeldingen/favicon-96p.png" sizes="96x96">
    <link rel="apple-touch-icon" href="afbeeldingen/favicon-120p.png">
    <link rel="apple-touch-icon" href="afbeeldingen/favicon-180p.png">
    <link rel="apple-touch-icon" href="afbeeldingen/favicon-152p.png">
    <link rel="apple-touch-icon" href="afbeeldingen/favicon-167p.png">
</head>
<body>

<iframe id="iframeHelper" width="100%" src="resultaten.php?lijst=<?php echo $_GET["lijst"]; ?>&admin=1" frameborder="0"></iframe>

<script>
setTimeout('$("#iframeHelper").height( $("#iframeHelper").contents().find("html").height() );', 500);
</script>


</body>
</html>
