<?php

namespace muzieklijsten;

/**
 * Generieke fout binnen het project
 */
class Muzieklijsten_Exception extends \Exception {}

/**
 * Databasefout. Bevat de foutmelding van SQL.
 */
class SQLException extends Muzieklijsten_Exception {}

/**
 * Databasefout voor MySQL error 1062: er_dup_entry
 */
class SQLException_DupEntry extends SQLException {}

/**
 * Databasefout voor MySQL error 1406: er_data_too_long
 */
class SQLException_DataTooLong extends SQLException {}

// Echte exceptions gebaseerd op ingebouwde PHP errors
class ErrorErrorException extends \ErrorException {}
class WarningErrorException extends \ErrorException {}
class ParseErrorException extends \ErrorException {}
class NoticeErrorException extends \ErrorException {}
class CoreErrorException extends \ErrorException {}
class CoreWarningErrorException extends \ErrorException {}
class CompileErrorException extends \ErrorException {}
class CompileWarningErrorException extends \ErrorException {}
class UserErrorException extends \ErrorException {}
class UserWarningErrorException extends \ErrorException {}
class UserNoticeErrorException extends \ErrorException {}
class StrictErrorException extends \ErrorException {}
class RecoverableErrorException extends \ErrorException {}
class DeprecatedErrorException extends \ErrorException {}
class UserDeprecatedErrorException extends \ErrorException {}

/**
 * Bij het benaderen van een niet-bestaand pad.
 */
class PadBestaatNiet extends \ErrorException {}

/**
 * Voor adressering van niet-bestaande elementen in arrays
 */
class IndexException extends \ErrorException {}

/**
 * Bij het opvragen van niet-bestaande objectproperties.
 */
class UndefinedPropertyException extends \ErrorException {}

class ConfigException extends Muzieklijsten_Exception {}

class GebruikersException extends Muzieklijsten_Exception {}

class OngeldigeInvoer extends GebruikersException {}

/**
 * Stemmer staat op de blacklist
 */
class BlacklistException extends Muzieklijsten_Exception {}

class ObjectEigenschapOntbreekt extends Muzieklijsten_Exception {}
