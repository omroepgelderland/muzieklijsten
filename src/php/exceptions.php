<?php

/**
 * @author Remy Glaser <rglaser@gld.nl>
 */

namespace muzieklijsten;

use gldstdlib\exception\GLDException;

class ConfigException extends GLDException
{
}

class GebruikersException extends GLDException
{
}

class OngeldigeInvoer extends GebruikersException
{
}

/**
 * Stemmer staat op de blacklist
 */
class BlacklistException extends GLDException
{
}

class ObjectEigenschapOntbreekt extends GLDException
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
class LegeVrijeKeuze extends GLDException
{
}
