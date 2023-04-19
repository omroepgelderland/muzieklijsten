<?php
namespace muzieklijsten;

require_once __DIR__.'/../vendor/autoload.php';

function readline_met_default( string $prompt, bool $mag_leeg = true, string $default = '' ): string {
	if ( $default !== '' ) {
		$prompt = "{$prompt} [{$default}]: ";
	} else {
		$prompt = "{$prompt}: ";
	}
	$ans = trim(readline($prompt));
	if ( $ans === '' && $default !== '' ) {
		$ans = $default;
	}
	if ( $ans === '' && !$mag_leeg ) {
		throw new Muzieklijsten_Exception('De invoer mag niet leeg zijn.');
	}
	return $ans;
}

// Configuratiebestand genereren.
$root_url = readline_met_default('Root-URL naar deze installatie van muzieklijsten', false);
$root_url = rtrim($root_url, '/').'/';
$config = [
	'organisatie' => readline_met_default('Naam organisatie/bedrijf'),
	'root_url' => $root_url,
	'privacy_url' => readline_met_default('URL naar het privacybeleid'),
	'nimbus_url' => readline_met_default('URL naar Nimbus'),
	'sql' => [
		'server' => $sql_server = readline_met_default('SQL-server', false, 'localhost'),
		'database' => $sql_database = readline_met_default('Naam van de database', false, 'muzieklijsten'),
		'user' => $sql_user = readline_met_default('SQL gebruikersnaam', false, 'muzieklijsten'),
		'password' => $sql_password = readline_met_default('Wachtwoord van de SQL-gebruiker (alleen als de gebruiker nog niet bestaat)', false)
	],
	'php_auth' => [
		'user' => readline_met_default('Gebruikersnaam voor de beheerdersinterface', false),
		'password' => readline_met_default('Wachtwoord voor de beheerdersinterface', false)
	],
	'mail' => [
		'sendmail_path' => '/usr/sbin/sendmail',
		'afzender' => readline_met_default('Afzender voor e-mails naar de redactie', false)
	]
];

$configdir = path_join(__DIR__, '..', 'config');
if ( !is_dir($configdir) ) {
	mkdir($configdir, 0770, true);
}
$configdir = realpath($configdir);
$config_pad = path_join($configdir, 'config.json');
file_put_contents(
	$config_pad,
	json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);
echo "De configuratie is opgeslagen in {$config_pad}. Je kunt dit bestand zo nodig aanpassen.\n";

$public_pad = realpath(path_join(__DIR__, '..', 'public'));
$root_host = parse_url($root_url, PHP_URL_HOST);
$root_url_pad = parse_url($root_url, PHP_URL_PATH);
$root_url_pad = rtrim($root_url_pad, '/');
$https_root_url = rtrim("https://{$root_host}/{$root_url_pad}/", '/').'/';
if ( $root_url_pad === '' ) {
	$alias_regel = '';
} else {
	$alias_regel = "Alias {$root_url_pad} {$public_pad}\n\n";
}
echo "Maak een regel in je webserver zodat {$root_url} verwijst naar {$public_pad}\n";

$apache_template = <<<EOT
{$alias_regel}<IfModule mod_ssl.c>
    <VirtualHost *:80>
        ServerName {$root_host}
        Redirect / {$https_root_url}
    </VirtualHost>

    <VirtualHost *:443>
        ServerName {$root_host}
        DocumentRoot {$public_pad}
        SSLEngine on
        SSLCertificateFile /.../cert.pem
        SSLCertificateKeyFile /.../privkey.pem
        SSLCertificateChainFile /.../chain.pem
    </VirtualHost>
</IfModule>
<IfModule !mod_ssl.c>
    <VirtualHost *:80>
        ServerName {$root_host}
        DocumentRoot {$public_pad}
    </VirtualHost>
</IfModule>

<Directory {$public_pad}>
    AllowOverride None
    Require all granted
</Directory>
EOT;
echo "Voorbeeld apache configuratie:\n{$apache_template}\n";

// Database initialiseren.
$sql_server = Config::get_instelling('sql', 'server');
$sql_database = Config::get_instelling('sql', 'database');
$sql_user = Config::get_instelling('sql', 'user');
$sql_password = Config::get_instelling('sql', 'password');
$db_root_user = readline_met_default('MySQL user met rechten voor maken van databases en gebruikers', false, 'root');
$db_root_password = readline_met_default('Wachtwoord (leeglaten bij authenticatie met auth_socket)');
$root_password_param = $db_root_password === '' ? '' : "-p '{$db_root_password}'";
$root_queries = <<<EOT
CREATE DATABASE IF NOT EXISTS `{$sql_database}`;
CREATE USER IF NOT EXISTS "{$sql_user}"@"{$sql_server}" IDENTIFIED BY "{$sql_password}";
GRANT ALL PRIVILEGES ON `{$sql_database}`.* TO "{$sql_user}"@"{$sql_server}";
EOT;
exec("sudo mysql -u {$db_root_user} {$root_password_param} -e '{$root_queries}'", $output, $result_code);
if ( $result_code !== 0 ) {
	echo $output."\n";
	echo <<<EOT
	Kan niet inloggen als root op MySQL. Voer de volgende queries zelf uit op je MySQL-server en druk daarna op enter.
	{$root_queries}
	EOT;
	readline();
}

$db = DB::getDB();
$db->multi_query(file_get_contents(path_join(__DIR__, 'install.sql')));
do {} while ( $db->next_result() );

echo <<<EOT
Installatie voltooid. Nadat je je webserver hebt ingesteld zijn de volgende interfaces bereikbaar:

Stemmodule voor publiek:
{$root_url}?lijst=[lijst id]
{$root_url}index.php?lijst=[lijst id]

Beheer van lijsten, plaatsen van nummers op een lijst, bekijken en beheren van resultaten:
{$root_url}admin.php

Losse nummers toevoegen aan de database, buiten Powergold om:
{$root_url}los_toevoegen.html

EOT;
