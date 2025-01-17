<?php

/**
 * @author Remy Glaser <rglaser@gld.nl>
 */

namespace muzieklijsten;

require_once __DIR__ . '/../vendor/autoload.php';

set_env();
$container = get_di_container();
/**
 * @var object{
 *     jaar: string,
 *     nummers_html_str: string,
 *     nummers_meta_str: string,
 *     og_image: string,
 *     og_url: string
 * } $data
 */
$data = $container->call(get_fbshare_data(...));

?>

<!DOCTYPE html>
<html lang="nl-NL" ng-app="fbShare" ng-controller="MainCtrl">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Delen op Facebook</title>
        <meta property="og:type" content="website">
        <meta property="fb:app_id" content="1269120393132176">
        <meta property="og:url" content="<?php echo $data->og_url; ?>">
        <meta property="og:locale" content="nl_NL">
        <meta property="og:image" content="<?php echo $data->og_image; ?>">
        <meta property="og:title" content="Dit is mijn keuze voor de Gelderse Top 100 <?php echo $data->jaar; ?>">
        <meta property="og:description" content="<?php echo $data->nummers_meta_str; ?>">
        <title>Mijn keuzes</title>
        <!-- Scripts prod en dev -->
        <script src="js/runtime.js" defer></script>
        <script src="js/admin-fbshare-los_toevoegen-muzieklijst.js" defer></script>
        <script src="js/fbshare.js" defer></script>
        <!-- Styles prod -->
        <link rel="stylesheet" href="css/admin-fbshare-los_toevoegen-muzieklijst.css">
        <link rel="stylesheet" href="css/fbshare.css">
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
        <div class="container">
            <img src="afbeeldingen/fbshare_top100.jpg" class="img-responsive headerimage">
            <h2>Dit is mijn keuze voor de Gelderse Top 100 <?php echo $data->jaar; ?>:</h2>
            <ol>
                <?php echo $data->nummers_html_str; ?>
            </ol>
            <p>Ook meedoen? <a href="https://www.gld.nl/de-gelderse-top-100">Klik hier!</a></p>
        </div>
    </body>
</html>
