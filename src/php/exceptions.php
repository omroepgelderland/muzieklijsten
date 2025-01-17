<?php

/**
 * @author Remy Glaser <rglaser@gld.nl>
 */

namespace muzieklijsten;

/**
 * Generieke fout binnen het project
 */
class MuzieklijstenException extends \Exception
{
}

/**
 * Databasefout. Bevat de foutmelding van SQL.
 */
class SQLException extends MuzieklijstenException
{
}

/**
 * Databasefout voor MySQL error 1062: er_dup_entry
 */
class SQLException_DupEntry extends SQLException
{
}

/**
 * Databasefout voor MySQL error 1406: er_data_too_long
 */
class SQLException_DataTooLong extends SQLException
{
}

/**
 * Bij het benaderen van een niet-bestaand pad.
 */
class PadBestaatNiet extends \ErrorException
{
}

/**
 * Voor adressering van niet-bestaande elementen in arrays
 */
class IndexException extends \ErrorException
{
}

/**
 * Bij het opvragen van niet-bestaande objectproperties.
 */
class UndefinedPropertyException extends \ErrorException
{
}

class ConfigException extends MuzieklijstenException
{
}

class GebruikersException extends MuzieklijstenException
{
}

class OngeldigeInvoer extends GebruikersException
{
}

/**
 * Stemmer staat op de blacklist
 */
class BlacklistException extends MuzieklijstenException
{
}

class ObjectEigenschapOntbreekt extends MuzieklijstenException
{
}

/**
 * Ongeldig lijst-ID
 */
class GeenLijstException extends GebruikersException
{
}

/**
 * Ingestuurde vrije keuze met lege artiest of titel.
 */
class LegeVrijeKeuze extends MuzieklijstenException
{
}
