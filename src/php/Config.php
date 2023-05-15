<?php
/**
 * 
 * @author Remy Glaser <rglaser@gld.nl>
 * @package muzieklijsten
 */

namespace muzieklijsten;

/**
 * Hiermee kunnen instellingen worden opgehaald uit de serverconfiguratie.
 * Dit is een singleton class.
 */
class Config {
	
	private static Config $config;
	private array $data;
	
	/**
	 * Maakt een nieuw object. Mag alleen vanuit deze class worden gedaan
	 */
	protected function __construct() {}
	
	/**
	 * Singleton classes kunnen niet worden gekloond.
	 */
	private function __clone() {}
	
	/**
	 * Haalt de JSON inhoud op.
	 * @return array Inhoud van het configuratiebestand
	 * @throws EPGException Als het configuratiebestand niet kan worden geladen.
	 */
	protected function _get_data(): array {
		if ( !isset($this->data) ) {
			try {
				$pad = __DIR__.'/../../config/config.json';
				$this->data = json_decode(file_get_contents($pad), true);
			} catch ( \Exception $e ) {
				throw new Muzieklijsten_Exception('Kan config.json niet laden.', 0, $e);
			}
		}
		return $this->data;
	}
	
	/**
	 * Geeft de niet-statische instantie van de Singletonclass.
	 * @return Config Object
	 */
	public static function get_obj(): Config {
		self::$config ??= new Config();
		return self::$config;
	}
	
	/**
	 * Haalt de JSON inhoud op.
	 * @return array Inhoud van het configuratiebestand
	 * @throws EPGException Als het configuratiebestand niet kan worden geladen.
	 */
	public static function get_data(): array {
		return self::get_obj()->_get_data();
	}
	
	/**
	 * Haalt een instelling op die in een sectie staat.
	 * Instellingen staan onder een variabel aantal niveaus, die met de functieparameters worden aangegeven.
	 * @param string $args,... Secties waaronder de instelling staat.
	 * @return mixed De waarde van de instelling
	 * @throws ConfigException Als de instelling niet kan worden gevonden.
	 */
	public static function get_instelling( string ...$args ) {
		$sectie = self::get_data();
		foreach ( func_get_args() as $param ) {
			if ( !is_array($sectie) || !array_key_exists($param, $sectie) ) {
				throw new ConfigException(sprintf(
						'De instelling %s kan niet worden gevonden.',
						implode('->', func_get_args()
				)));
			}
			$sectie = $sectie[$param];
		}
		return $sectie;
	}

	/**
	 * Geeft het Google Recaptcha object
	 * @return \ReCaptcha\ReCaptcha
	 */
	public static function get_recaptcha(): \ReCaptcha\ReCaptcha {
		return new \ReCaptcha\ReCaptcha(static::get_instelling('recaptcha', 'secret'));
	}
}
