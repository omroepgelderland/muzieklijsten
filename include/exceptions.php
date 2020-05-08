<?php

/**
 * Generieke fout binnen het project
 */
class Muzieklijsten_Exception extends Exception {}

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
