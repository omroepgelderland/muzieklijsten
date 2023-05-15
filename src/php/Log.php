<?php
/**
 * Logging.
 * 
 * @author Remy Glaser <rglaser@gld.nl>
 * @package muzieklijsten
 */

namespace muzieklijsten;

/**
 * Logger
 * Singleton class
 */
class Log {
	
	private static Log $obj;
	protected \Laminas\Log\Logger $logger;
	
	/**
	* Singleton classes kunnen niet worden gekloond.
	 */
	private function __clone() {}
	
	protected static function get_obj(): static {
		return self::$obj ??= new static();
	}
	
	protected function _get_logger( ?string $bestandsnaam=null ): \Laminas\Log\Logger {
		if ( !isset($this->logger) ) {
			$this->logger = new \Laminas\Log\Logger();
			$this->add_writers($bestandsnaam);
		}
		return $this->logger;
	}
	
	protected static function get_logger( ?string $bestandsnaam=null ): \Laminas\Log\Logger {
		return self::get_obj()->_get_logger($bestandsnaam);
	}
	
	protected function add_writers( ?string $bestandsnaam=null ): void {
		if ( is_dev() || is_cli() ) {
			$loglevel = new \Laminas\Log\Filter\Priority(\Laminas\Log\Logger::DEBUG);
		} else {
			$loglevel = new \Laminas\Log\Filter\Priority(\Laminas\Log\Logger::INFO);
		}
		
		// Commandline writer
		if ( is_cli() ) {
			$writer = new \Laminas\Log\Writer\Stream('php://output');
			$writer->addFilter($loglevel);
			$this->logger->addWriter($writer);
		}
		
		// Bestandslog
		// Altijd naar bestand schrijven, ook als scripts interactief wordt uitgevoerd.
		$bestand_writer = new \Laminas\Log\Writer\Stream($this->get_pad($bestandsnaam), null, null, 0664);
		$bestand_writer->addFilter($loglevel);
		$this->logger->addWriter($bestand_writer);
		
		// Logs per errortype
		$noticebestand_writer = new \Laminas\Log\Writer\Stream($this->get_pad('errors5_notice'), null, null, 0664);
		$noticebestand_writer->addFilter(new \Laminas\Log\Filter\Priority(\Laminas\Log\Logger::NOTICE, '=='));
		$this->logger->addWriter($noticebestand_writer);
		$warnbestand_writer = new \Laminas\Log\Writer\Stream($this->get_pad('errors4_warn'), null, null, 0664);
		$warnbestand_writer->addFilter(new \Laminas\Log\Filter\Priority(\Laminas\Log\Logger::WARN, '=='));
		$this->logger->addWriter($warnbestand_writer);
		$errbestand_writer = new \Laminas\Log\Writer\Stream($this->get_pad('errors3_err'), null, null, 0664);
		$errbestand_writer->addFilter(new \Laminas\Log\Filter\Priority(\Laminas\Log\Logger::ERR, '=='));
		$this->logger->addWriter($errbestand_writer);
		$critbestand_writer = new \Laminas\Log\Writer\Stream($this->get_pad('errors2_crit'), null, null, 0664);
		$critbestand_writer->addFilter(new \Laminas\Log\Filter\Priority(\Laminas\Log\Logger::CRIT, '=='));
		$this->logger->addWriter($critbestand_writer);
		$alertbestand_writer = new \Laminas\Log\Writer\Stream($this->get_pad('errors1_alert'), null, null, 0664);
		$alertbestand_writer->addFilter(new \Laminas\Log\Filter\Priority(\Laminas\Log\Logger::ALERT, '=='));
		$this->logger->addWriter($alertbestand_writer);
		$emergbestand_writer = new \Laminas\Log\Writer\Stream($this->get_pad('errors0_emerg'), null, null, 0664);
		$emergbestand_writer->addFilter(new \Laminas\Log\Filter\Priority(\Laminas\Log\Logger::EMERG, '=='));
		$this->logger->addWriter($emergbestand_writer);
	}
	
	/**
	 * Geeft het pad naar het logbestand.
	 * @param string $bestandsnaam Gebruik deze naam in plaats van de naam van het script (optioneel)
	 * @return string
	 */
	protected function get_pad( ?string $bestandsnaam=null ): string {
		$bestandsnaam ??= pathinfo(get_hoofdscript_pad())['filename'];
		$pad = path_join(
			__DIR__,
			'..',
			'..',
			'data',
			'log',
			sprintf(
				'%s_%s.log',
				$bestandsnaam,
				(new \DateTime())->format('Y-m-d')
			)
		);
		if ( !is_dir(dirname($pad)) ) {
			mkdir(dirname($pad), 0775, true);
		}
		return $pad;
	}

	/**
	 * Stelt een andere bestandsnaam in (zonder extensie).
	 * @param string $bestandsnaam
	 */
	public static function set_bestandsnaam( string $bestandsnaam ): void {
		self::get_obj()->_set_bestandsnaam($bestandsnaam);
	}
	
	/**
	 * Stelt een andere bestandsnaam in (zonder extensie).
	 * @param string $bestandsnaam
	 */
	protected function _set_bestandsnaam( string $bestandsnaam ): void {
		unset($this->logger);
		$this->get_logger($bestandsnaam);
	}
	
	/**
	 * 
	 * @param mixed $args,...
	 */
	public static function emerg( ...$args ): void {
		self::schrijf_log(self::get_logger()::EMERG, ...$args);
	}
	
	/**
	 * 
	 * @param mixed $args,...
	 */
	public static function alert( ...$args ): void {
		self::schrijf_log(self::get_logger()::ALERT, ...$args);
	}
	
	/**
	 * 
	 * @param mixed $args,...
	 */
	public static function crit( ...$args ): void {
		self::schrijf_log(self::get_logger()::CRIT, ...$args);
	}
	
	/**
	 * 
	 * @param mixed $args,...
	 */
	public static function err( ...$args ): void {
		self::schrijf_log(self::get_logger()::ERR, ...$args);
	}
	
	/**
	 * 
	 * @param mixed $args,...
	 */
	public static function warn( ...$args ): void {
		self::schrijf_log(self::get_logger()::WARN, ...$args);
	}
	
	/**
	 * 
	 * @param mixed $args,...
	 */
	public static function notice( ...$args ): void {
		self::schrijf_log(self::get_logger()::NOTICE, ...$args);
	}
	
	/**
	 * 
	 * @param mixed $args,...
	 */
	public static function info( ...$args ): void {
		self::schrijf_log(self::get_logger()::INFO, ...$args);
	}
	
	/**
	 * 
	 * @param mixed $args,...
	 */
	public static function debug( ...$args ): void {
		self::schrijf_log(self::get_logger()::DEBUG, ...$args);
	}
	
	private static function schrijf_log( $priority, ...$args ): void {
		if ( count($args) === 1 ) {
			$message = $args[0];
		} else {
			$message = sprintf(...$args);
		}
		self::get_logger()->log($priority, $message);
	}
	
	/**
	 * Sluit het log.
	 * Nuttig voor continue processen om niet steeds het log open te hebben
	 * staan en de bestandsnaam van het log aan te passen aan de datum.
	 */
	public static function sluiten(): void {
		unset(self::$obj);
	}
	
}
