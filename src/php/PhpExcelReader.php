<?php

/**
 * A class for reading Microsoft Excel Spreadsheets. It supports both xls and xlsx types.
 *
 * Originally developed by Vadim Tkachenko under the name PHPExcelReader.
 * ( http://sourceforge.net/projects/phpexcelreader )
 * Based on the Java version by Andy Khan (http://www.andykhan.com).  Now
 * maintained by David Sanders.  Reads only Biff 7 and Biff 8 formats.
 * Changed the code from PHP 4 to PHP 5 :   CoursesWeb ( http://coursesweb.net/ )
 *
 * PHP versions 5.3
 *
 * @link http://pear.php.net/package/Spreadsheet_Excel_Reader
 *
 * @author Vadim Tkachenko <vt@apachephp.com>
 *
 * @license http://www.php.net/license/3_0.txt  PHP License 3.0
 *
 * @version CVS: $Id: reader.php 19 2007-03-13 12:42:41Z shangxiao $
 */

namespace muzieklijsten;

class PhpExcelReader
{
    private const BIFF8 = 0x600;
    private const BIFF7 = 0x500;
    private const WORKBOOKGLOBALS = 0x5;
    private const WORKSHEET = 0x10;
    private const TYPE_BOF = 0x809;
    private const TYPE_EOF = 0x0a;
    private const TYPE_BOUNDSHEET = 0x85;
    private const TYPE_DIMENSION = 0x200;
    private const TYPE_ROW = 0x208;
    private const TYPE_DBCELL = 0xd7;
    private const TYPE_FILEPASS = 0x2f;
    private const TYPE_NOTE = 0x1c;
    private const TYPE_TXO = 0x1b6;
    private const TYPE_RK = 0x7e;
    private const TYPE_RK2 = 0x27e;
    private const TYPE_MULRK = 0xbd;
    private const TYPE_MULBLANK = 0xbe;
    private const TYPE_INDEX = 0x20b;
    private const TYPE_SST = 0xfc;
    private const TYPE_EXTSST = 0xff;
    private const TYPE_CONTINUE = 0x3c;
    private const TYPE_LABEL = 0x204;
    private const TYPE_LABELSST = 0xfd;
    private const TYPE_NUMBER = 0x203;
    private const TYPE_NAME = 0x18;
    private const TYPE_ARRAY = 0x221;
    private const TYPE_STRING = 0x207;
    private const TYPE_FORMULA = 0x406;
    private const TYPE_FORMULA2 = 0x6;
    private const TYPE_FORMAT = 0x41e;
    private const TYPE_XF = 0xe0;
    private const TYPE_BOOLERR = 0x205;
    private const TYPE_UNKNOWN = 0xffff;
    private const TYPE_NINETEENFOUR = 0x22;
    private const TYPE_MERGEDCELLS = 0xE5;
    private const UTCOFFSETDAYS = 25569;
    private const UTCOFFSETDAYS1904 = 24107;
    private const MSINADAY = 86400;
    private const DEF_NUM_FORMAT = "%s";

    /**
     * Array of worksheets found
     */
    public $boundsheets = [];

    /**
     * Array of format records found
     */
    public $formatRecords = [];

    public $sst = [];

    /**
     * Array of worksheets
     *
     * The data is stored in 'cells' and the meta-data is stored in an array
     * called 'cellsInfo'
     *
     * Example:
     *
     * $sheets  -->  'cells'  -->  row --> column --> Interpreted value
     *          -->  'cellsInfo' --> row --> column --> 'type' - Can be 'date', 'number', or 'unknown'
     *                                            --> 'raw' - The raw data that Excel stores for that data cell
     */
    public $sheets = [];

    /**
     * The string data returned by OLE
     */
    public $data;

    /**
     * OLE object for reading the file
     */
    private $_ole;

    /**
     * Default encoding
     */
    private $_defaultEncoding;

    /**
     * Default number format
     */
    private $_defaultFormat = self::DEF_NUM_FORMAT;

    /**
     * @todo
     * List of formats to use for each column
     */
    private $_columnsFormat = [];

    /**
     * @todo
     */
    private $_rowoffset = 1;

    /**
     * @todo
     */
    private $_coloffset = 1;

    /**
     * List of default date formats used by Excel
     */
    public $dateFormats = [
        0xe => "d/m/Y",
        0xf => "d-M-Y",
        0x10 => "d-M",
        0x11 => "M-Y",
        0x12 => "h:i a",
        0x13 => "h:i:s a",
        0x14 => "H:i",
        0x15 => "H:i:s",
        0x16 => "d/m/Y H:i",
        0x2d => "i:s",
        0x2e => "H:i:s",
        0x2f => "i:s.S",
    ];

    /**
     * Default number formats used by Excel
     */
    public $numberFormats = [
        0x1 => "%1.0f",     // "0"
        0x2 => "%1.2f",     // "0.00",
        0x3 => "%1.0f",     //"#,##0",
        0x4 => "%1.2f",     //"#,##0.00",
        0x5 => "%1.0f",     /*"$#,##0;($#,##0)",*/
        0x6 => '$%1.0f',    /*"$#,##0;($#,##0)",*/
        0x7 => '$%1.2f',    //"$#,##0.00;($#,##0.00)",
        0x8 => '$%1.2f',    //"$#,##0.00;($#,##0.00)",
        0x9 => '%1.0f%%',   // "0%"
        0xa => '%1.2f%%',   // "0.00%"
        0xb => '%1.2f',     // 0.00E00",
        0x25 => '%1.0f',    // "#,##0;(#,##0)",
        0x26 => '%1.0f',    //"#,##0;(#,##0)",
        0x27 => '%1.2f',    //"#,##0.00;(#,##0.00)",
        0x28 => '%1.2f',    //"#,##0.00;(#,##0.00)",
        0x29 => '%1.0f',    //"#,##0;(#,##0)",
        0x2a => '$%1.0f',   //"$#,##0;($#,##0)",
        0x2b => '%1.2f',    //"#,##0.00;(#,##0.00)",
        0x2c => '$%1.2f',   //"$#,##0.00;($#,##0.00)",
        0x30 => '%1.0f',
    ];   //"##0.0E0";

    public $_encoderFunction;
    public $nineteenFour;
    public $sn;
    public $rectype;
    public $multiplier;
    public $numRows;
    public $curformat;

    /**
     * Constructor
     *
     * Some basic initialisation
     */
    public function __construct()
    {
        $this->_ole = new OLERead();
        $this->setUTFEncoder('iconv');
    }

    /**
     * Set the encoding method
     *
     * @param $encoding Encoding to use
     */
    public function setOutputEncoding(string $encoding)
    {
        $this->_defaultEncoding = $encoding;
    }

    /**
     *  $encoder = 'iconv' or 'mb'
     *  set iconv if you would like use 'iconv' for encode UTF-16LE to your encoding
     *  set mb if you would like use 'mb_convert_encoding' for encode UTF-16LE to your encoding
     *
     * @param $encoder Encoding type to use.  Either 'iconv' or 'mb'
     */
    public function setUTFEncoder(string $encoder = 'iconv')
    {
        $this->_encoderFunction = '';

        if ($encoder == 'iconv') {
            $this->_encoderFunction = function_exists('iconv') ? 'iconv' : '';
        } elseif ($encoder == 'mb') {
            $this->_encoderFunction = function_exists('mb_convert_encoding') ?
                                      'mb_convert_encoding' :
                                      '';
        }
    }

    /**
     * @todo
     */
    public function setRowColOffset($iOffset)
    {
        $this->_rowoffset = $iOffset;
        $this->_coloffset = $iOffset;
    }

    /**
     * Set the default number format
     *
     * @param $sFormat Default format
     */
    public function setDefaultFormat($sFormat)
    {
        $this->_defaultFormat = $sFormat;
    }

    /**
     * Force a column to use a certain format
     *
     * @param $column Column number
     * @param $sFormat Format
     */
    public function setColumnFormat(int $column, string $sFormat)
    {
        $this->_columnsFormat[$column] = $sFormat;
    }

    /**
     * Read the spreadsheet file using OLE, then parse
     *
     * @param $sFilename filename
     *
     * @todo return a valid value
     */
    public function read($sFileName)
    {

        $res = $this->_ole->read($sFileName);

        // oops, something goes wrong (Darko Miljanovic)
        if ($res === false) {
            // check error code
            if ($this->_ole->error == 1) {
            // bad file
                die('The filename ' . $sFileName . ' is not readable');
            }
            // check other error codes here (eg bad fileformat, etc...)
        }

        $this->data = $this->_ole->getWorkBook();

        $this->parse();
    }

    /**
     * Parse a workbook
     */
    private function parse(): bool
    {
        $pos = 0;

        $code = ord($this->data[$pos]) | ord($this->data[$pos + 1]) << 8;
        $length = ord($this->data[$pos + 2]) | ord($this->data[$pos + 3]) << 8;

        $version = ord($this->data[$pos + 4]) | ord($this->data[$pos + 5]) << 8;
        $substreamType = ord($this->data[$pos + 6]) | ord($this->data[$pos + 7]) << 8;

        if (
            ($version != self::BIFF8) &&
            ($version != self::BIFF7)
        ) {
            return false;
        }

        if ($substreamType != self::WORKBOOKGLOBALS) {
            return false;
        }

        $pos += $length + 4;

        $code = ord($this->data[$pos]) | ord($this->data[$pos + 1]) << 8;
        $length = ord($this->data[$pos + 2]) | ord($this->data[$pos + 3]) << 8;

        while ($code != self::TYPE_EOF) {
            switch ($code) {
                case self::TYPE_SST:
                    //echo "Type_SST\n";
                     $spos = $pos + 4;
                     $limitpos = $spos + $length;
                     $uniqueStrings = $this->getInt4d($this->data, $spos + 4);
                                                $spos += 8;
                    for ($i = 0; $i < $uniqueStrings; $i++) {
        // Read in the number of characters
                        if ($spos == $limitpos) {
                                      $opcode = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
                                      $conlength = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
                            if ($opcode != 0x3c) {
                                return -1;
                            }
                                      $spos += 4;
                                      $limitpos = $spos + $conlength;
                        }
                             $numChars = ord($this->data[$spos]) | (ord($this->data[$spos + 1]) << 8);
                             $spos += 2;
                             $optionFlags = ord($this->data[$spos]);
                             $spos++;
                                           $asciiEncoding = (($optionFlags & 0x01) == 0);
                             $extendedString = ( ($optionFlags & 0x04) != 0);

                             // See if string contains formatting information
                             $richString = ( ($optionFlags & 0x08) != 0);

                        if ($richString) {
                                                 // Read in the crun
                                                   $formattingRuns = ord($this->data[$spos]) | (ord($this->data[$spos + 1]) << 8);
                                                   $spos += 2;
                        }

                        if ($extendedString) {
                          // Read in cchExtRst
                            $extendedRunLength = $this->getInt4d($this->data, $spos);
                            $spos += 4;
                        }

                                                              $len = $asciiEncoding ? $numChars : $numChars * 2;
                        if ($spos + $len < $limitpos) {
                                        $retstr = substr($this->data, $spos, $len);
                                        $spos += $len;
                        } else {
                                // found countinue
                                $retstr = substr($this->data, $spos, $limitpos - $spos);
                                $bytesRead = $limitpos - $spos;
                                $charsLeft = $numChars - ($asciiEncoding ? $bytesRead : $bytesRead / 2);
                                $spos = $limitpos;

                            while ($charsLeft > 0) {
                                                            $opcode = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
                                                            $conlength = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
                                if ($opcode != 0x3c) {
                                    return -1;
                                }
                                                                 $spos += 4;
                                                                 $limitpos = $spos + $conlength;
                                                                 $option = ord($this->data[$spos]);
                                                                 $spos += 1;
                                if ($asciiEncoding && ($option == 0)) {
                                    $len = min($charsLeft, $limitpos - $spos); // min($charsLeft, $conlength);
                                    $retstr .= substr($this->data, $spos, $len);
                                    $charsLeft -= $len;
                                    $asciiEncoding = true;
                                } elseif (!$asciiEncoding && ($option != 0)) {
                                    $len = min($charsLeft * 2, $limitpos - $spos); // min($charsLeft, $conlength);
                                    $retstr .= substr($this->data, $spos, $len);
                                    $charsLeft -= $len / 2;
                                    $asciiEncoding = false;
                                } elseif (!$asciiEncoding && ($option == 0)) {
                 // Bummer - the string starts off as Unicode, but after the
                 // continuation it is in straightforward ASCII encoding
                                    $len = min($charsLeft, $limitpos - $spos); // min($charsLeft, $conlength);
                                    for ($j = 0; $j < $len; $j++) {
                                                                            $retstr .= $this->data[$spos + $j] . chr(0);
                                    }
                                                                     $charsLeft -= $len;
                                                                     $asciiEncoding = false;
                                } else {
                                    $newstr = '';
                                    for ($j = 0; $j < strlen($retstr); $j++) {
                                                                     $newstr = $retstr[$j] . chr(0);
                                    }
                                                                     $retstr = $newstr;
                                                                     $len = min($charsLeft * 2, $limitpos - $spos); // min($charsLeft, $conlength);
                                                                     $retstr .= substr($this->data, $spos, $len);
                                                                     $charsLeft -= $len / 2;
                                                                     $asciiEncoding = false;
                                                           //echo "Izavrat\n";
                                }
                                                                 $spos += $len;
                            }
                        }
                                                              $retstr = $asciiEncoding ? $retstr : $this->encodeUTF16($retstr);

                        if ($richString) {
                                  $spos += 4 * $formattingRuns;
                        }

                                                // For extended strings, skip over the extended string data
                        if ($extendedString) {
                            $spos += $extendedRunLength;
                        }
                                                              $this->sst[] = $retstr;
                    }
                    break;

                case self::TYPE_FILEPASS:
                    return false;
                    break;
                case self::TYPE_NAME:
                    break;
                case self::TYPE_FORMAT:
                        $indexCode = ord($this->data[$pos + 4]) | ord($this->data[$pos + 5]) << 8;

                    if ($version == self::BIFF8) {
                        $numchars = ord($this->data[$pos + 6]) | ord($this->data[$pos + 7]) << 8;
                        if (ord($this->data[$pos + 8]) == 0) {
                            $formatString = substr($this->data, $pos + 9, $numchars);
                        } else {
                            $formatString = substr($this->data, $pos + 9, $numchars * 2);
                        }
                    } else {
                        $numchars = ord($this->data[$pos + 6]);
                        $formatString = substr($this->data, $pos + 7, $numchars * 2);
                    }

                    $this->formatRecords[$indexCode] = $formatString;
                    break;
                case self::TYPE_XF:
                        //global $dateFormats, $numberFormats;
                        $indexCode = ord($this->data[$pos + 6]) | ord($this->data[$pos + 7]) << 8;
                    if (array_key_exists($indexCode, $this->dateFormats)) {
                        $this->formatRecords['xfrecords'][] = [
                            'type' => 'date',
                            'format' => $this->dateFormats[$indexCode],
                        ];
                    } elseif (array_key_exists($indexCode, $this->numberFormats)) {
                        $this->formatRecords['xfrecords'][] = [
                            'type' => 'number',
                            'format' => $this->numberFormats[$indexCode],
                        ];
                    } else {
                        $isdate = false;
                        if ($indexCode > 0) {
                            if (isset($this->formatRecords[$indexCode])) {
                                $formatstr = $this->formatRecords[$indexCode];
                            }
                            if ($formatstr) {
                                if (preg_match("/[^hmsday\/\-:\s]/i", $formatstr) == 0) { // found day and time format
                                    $isdate = true;
                                    $formatstr = str_replace('mm', 'i', $formatstr);
                                    $formatstr = str_replace('h', 'H', $formatstr);
                                }
                            }
                        }

                        if ($isdate) {
                            $this->formatRecords['xfrecords'][] = [
                                'type' => 'date',
                                'format' => $formatstr,
                            ];
                        } else {
                            $this->formatRecords['xfrecords'][] = [
                                'type' => 'other',
                                'format' => '',
                                'code' => $indexCode,
                            ];
                        }
                    }
                    break;
                case self::TYPE_NINETEENFOUR:
                    $this->nineteenFour = (ord($this->data[$pos + 4]) == 1);
                    break;
                case self::TYPE_BOUNDSHEET:
                        $rec_offset = $this->getInt4d($this->data, $pos + 4);
                        $rec_typeFlag = ord($this->data[$pos + 8]);
                        $rec_visibilityFlag = ord($this->data[$pos + 9]);
                        $rec_length = ord($this->data[$pos + 10]);

                    if ($version == self::BIFF8) {
                        $chartype =  ord($this->data[$pos + 11]);
                        if ($chartype == 0) {
                            $rec_name    = substr($this->data, $pos + 12, $rec_length);
                        } else {
                            $rec_name    = $this->encodeUTF16(substr($this->data, $pos + 12, $rec_length * 2));
                        }
                    } elseif ($version == self::BIFF7) {
                            $rec_name    = substr($this->data, $pos + 11, $rec_length);
                    }
                    $this->boundsheets[] = ['name' => $rec_name,
                        'offset' => $rec_offset,
                    ];

                    break;
            }

            $pos += $length + 4;
            $code = ord($this->data[$pos]) | ord($this->data[$pos + 1]) << 8;
            $length = ord($this->data[$pos + 2]) | ord($this->data[$pos + 3]) << 8;
        }

        foreach ($this->boundsheets as $key => $val) {
            $this->sn = $key;
            $this->parsesheet($val['offset']);
        }
        return true;
    }

    /**
     * Parse a worksheet
     */
    private function parsesheet($spos)
    {
        $cont = true;
        // read BOF
        $code = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
        $length = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;

        $version = ord($this->data[$spos + 4]) | ord($this->data[$spos + 5]) << 8;
        $substreamType = ord($this->data[$spos + 6]) | ord($this->data[$spos + 7]) << 8;

        if (($version != self::BIFF8) && ($version != self::BIFF7)) {
            return -1;
        }

        if ($substreamType != self::WORKSHEET) {
            return -2;
        }

        $spos += $length + 4;

        while ($cont) {
            $lowcode = ord($this->data[$spos]);
            if ($lowcode == self::TYPE_EOF) {
                break;
            }
            $code = $lowcode | ord($this->data[$spos + 1]) << 8;
            $length = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
            $spos += 4;
            $this->sheets[$this->sn]['maxrow'] = $this->_rowoffset - 1;
            $this->sheets[$this->sn]['maxcol'] = $this->_coloffset - 1;
            unset($this->rectype);
            $this->multiplier = 1; // need for format with %
            switch ($code) {
                case self::TYPE_DIMENSION:
                    //echo 'Type_DIMENSION ';
                    if (!isset($this->numRows)) {
                        if (($length == 10) || ($version == self::BIFF7)) {
                            $this->sheets[$this->sn]['numRows'] = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
                            $this->sheets[$this->sn]['numCols'] = ord($this->data[$spos + 6]) | ord($this->data[$spos + 7]) << 8;
                        } else {
                            $this->sheets[$this->sn]['numRows'] = ord($this->data[$spos + 4]) | ord($this->data[$spos + 5]) << 8;
                            $this->sheets[$this->sn]['numCols'] = ord($this->data[$spos + 10]) | ord($this->data[$spos + 11]) << 8;
                        }
                    }
                    break;
                case self::TYPE_MERGEDCELLS:
                    $cellRanges = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
                    for ($i = 0; $i < $cellRanges; $i++) {
                        $fr =  ord($this->data[$spos + 8 * $i + 2]) | ord($this->data[$spos + 8 * $i + 3]) << 8;
                        $lr =  ord($this->data[$spos + 8 * $i + 4]) | ord($this->data[$spos + 8 * $i + 5]) << 8;
                        $fc =  ord($this->data[$spos + 8 * $i + 6]) | ord($this->data[$spos + 8 * $i + 7]) << 8;
                        $lc =  ord($this->data[$spos + 8 * $i + 8]) | ord($this->data[$spos + 8 * $i + 9]) << 8;
                        //$this->sheets[$this->sn]['mergedCells'][] = [$fr + 1, $fc + 1, $lr + 1, $lc + 1];
                        if ($lr - $fr > 0) {
                            $this->sheets[$this->sn]['cellsInfo'][$fr + 1][$fc + 1]['rowspan'] = $lr - $fr + 1;
                        }
                        if ($lc - $fc > 0) {
                            $this->sheets[$this->sn]['cellsInfo'][$fr + 1][$fc + 1]['colspan'] = $lc - $fc + 1;
                        }
                    }
                    break;
                case self::TYPE_RK:
                case self::TYPE_RK2:
                    $row = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
                    $column = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
                    $rknum = $this->getInt4d($this->data, $spos + 6);
                    $numValue = $this->getIEEE754($rknum);
                    if ($this->isDate($spos)) {
                        [$string, $raw] = $this->createDate($numValue);
                    } else {
                        $raw = $numValue;
                        if (isset($this->_columnsFormat[$column + 1])) {
                                $this->curformat = $this->_columnsFormat[$column + 1];
                        }
                        $string = sprintf($this->curformat, $numValue * $this->multiplier);
                    }
                    $this->addcell($row, $column, $string, $raw);
                    break;
                case self::TYPE_LABELSST:
                        $row        = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
                        $column     = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
                        $xfindex    = ord($this->data[$spos + 4]) | ord($this->data[$spos + 5]) << 8;
                        $index  = $this->getInt4d($this->data, $spos + 6);
                        $this->addcell($row, $column, $this->sst[$index]);
                    break;
                case self::TYPE_MULRK:
                    $row        = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
                    $colFirst   = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
                    $colLast    = ord($this->data[$spos + $length - 2]) | ord($this->data[$spos + $length - 1]) << 8;
                    $columns    = $colLast - $colFirst + 1;
                    $tmppos = $spos + 4;
                    for ($i = 0; $i < $columns; $i++) {
                        $numValue = $this->getIEEE754($this->getInt4d($this->data, $tmppos + 2));
                        if ($this->isDate($tmppos - 4)) {
                            [$string, $raw] = $this->createDate($numValue);
                        } else {
                            $raw = $numValue;
                            if (isset($this->_columnsFormat[$colFirst + $i + 1])) {
                                        $this->curformat = $this->_columnsFormat[$colFirst + $i + 1];
                            }
                            $string = sprintf($this->curformat, $numValue * $this->multiplier);
                        }
                        $tmppos += 6;
                        $this->addcell($row, $colFirst + $i, $string, $raw);
                    }
                    break;
                case self::TYPE_NUMBER:
                    $row    = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
                    $column = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
                    $tmp = unpack("ddouble", substr($this->data, $spos + 6, 8)); // It machine machine dependent
                    if ($this->isDate($spos)) {
                        [$string, $raw] = $this->createDate($tmp['double']);
                    } else {
                        //$raw = $tmp[''];
                        if (isset($this->_columnsFormat[$column + 1])) {
                                $this->curformat = $this->_columnsFormat[$column + 1];
                        }
                        $raw = $this->createNumber($spos);
                        $string = sprintf($this->curformat, $raw * $this->multiplier);
                    }
                    $this->addcell($row, $column, $string, $raw);
                    break;
                case self::TYPE_FORMULA:
                case self::TYPE_FORMULA2:
                    $row    = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
                    $column = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
                    if ((ord($this->data[$spos + 6]) == 0) && (ord($this->data[$spos + 12]) == 255) && (ord($this->data[$spos + 13]) == 255)) {
                        //String formula. Result follows in a STRING record
                    } elseif ((ord($this->data[$spos + 6]) == 1) && (ord($this->data[$spos + 12]) == 255) && (ord($this->data[$spos + 13]) == 255)) {
                        //Boolean formula. Result is in +2; 0=false,1=true
                    } elseif ((ord($this->data[$spos + 6]) == 2) && (ord($this->data[$spos + 12]) == 255) && (ord($this->data[$spos + 13]) == 255)) {
                        //Error formula. Error code is in +2;
                    } elseif ((ord($this->data[$spos + 6]) == 3) && (ord($this->data[$spos + 12]) == 255) && (ord($this->data[$spos + 13]) == 255)) {
                        //Formula result is a null string.
                    } else {
                        // result is a number, so first 14 bytes are just like a _NUMBER record
                        $tmp = unpack("ddouble", substr($this->data, $spos + 6, 8)); // It machine machine dependent
                        if ($this->isDate($spos)) {
                            [$string, $raw] = $this->createDate($tmp['double']);
                        } else {
                            //$raw = $tmp[''];
                            if (isset($this->_columnsFormat[$column + 1])) {
                                    $this->curformat = $this->_columnsFormat[$column + 1];
                            }
                            $raw = $this->createNumber($spos);
                            $string = sprintf($this->curformat, $raw * $this->multiplier);
                        }
                        $this->addcell($row, $column, $string, $raw);
                    }
                    break;
                case self::TYPE_BOOLERR:
                    $row    = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
                    $column = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
                    $string = ord($this->data[$spos + 6]);
                    $this->addcell($row, $column, $string);
                    break;
                case self::TYPE_ROW:
                case self::TYPE_DBCELL:
                case self::TYPE_MULBLANK:
                    break;
                case self::TYPE_LABEL:
                    $row    = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
                    $column = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
                    $this->addcell($row, $column, substr($this->data, $spos + 8, ord($this->data[$spos + 6]) | ord($this->data[$spos + 7]) << 8));
                    break;

                case self::TYPE_EOF:
                    $cont = false;
                    break;
                default:
                    break;
            }
            $spos += $length;
        }

        if (!isset($this->sheets[$this->sn]['numRows'])) {
             $this->sheets[$this->sn]['numRows'] = $this->sheets[$this->sn]['maxrow'];
        }
        if (!isset($this->sheets[$this->sn]['numCols'])) {
             $this->sheets[$this->sn]['numCols'] = $this->sheets[$this->sn]['maxcol'];
        }
    }

    /**
     * Check whether the current record read is a date
     *
     * @return bool True if date, false otherwise
     */
    public function isDate($spos): bool
    {
        $xfindex = ord($this->data[$spos + 4]) | ord($this->data[$spos + 5]) << 8;
        if ($this->formatRecords['xfrecords'][$xfindex]['type'] == 'date') {
            $this->curformat = $this->formatRecords['xfrecords'][$xfindex]['format'];
            $this->rectype = 'date';
            return true;
        } else {
            if ($this->formatRecords['xfrecords'][$xfindex]['type'] == 'number') {
                $this->curformat = $this->formatRecords['xfrecords'][$xfindex]['format'];
                $this->rectype = 'number';
                if (($xfindex == 0x9) || ($xfindex == 0xa)) {
                    $this->multiplier = 100;
                }
            } else {
                $this->curformat = $this->_defaultFormat;
                $this->rectype = 'unknown';
            }
            return false;
        }
    }

    /**
     * Convert the raw Excel date into a human readable format
     *
     * Dates in Excel are stored as number of seconds from an epoch.  On
     * Windows, the epoch is 30/12/1899 and on Mac it's 01/01/1904
     *
     * @param $numValue The raw Excel value to convert
     *
     * @return array First element is the converted date, the second element is number a unix timestamp
     */
    private function createDate(int $numValue)
    {
        if ($numValue > 1) {
            $utcDays = $numValue - ($this->nineteenFour ? self::UTCOFFSETDAYS1904 : self::UTCOFFSETDAYS);
            $utcValue = round(($utcDays + 1) * self::MSINADAY);
            $string = date($this->curformat, $utcValue);
            $raw = $utcValue;
        } else {
            $raw = $numValue;
            $hours = floor($numValue * 24);
            $mins = floor($numValue * 24 * 60) - $hours * 60;
            $secs = floor($numValue * self::MSINADAY) - $hours * 60 * 60 - $mins * 60;
            $string = date($this->curformat, mktime($hours, $mins, $secs));
        }

        return [$string, $raw];
    }

    public function createNumber($spos)
    {
        $rknumhigh = $this->getInt4d($this->data, $spos + 10);
        $rknumlow = $this->getInt4d($this->data, $spos + 6);
        //for ($i=0; $i<8; $i++) { echo ord($this->data[$i+$spos+6]) . " "; } echo "<br>";
        $sign = ($rknumhigh & 0x80000000) >> 31;
        $exp =  ($rknumhigh & 0x7ff00000) >> 20;
        $mantissa = 0x100000 | ($rknumhigh & 0x000fffff);
        $mantissalow1 = ($rknumlow & 0x80000000) >> 31;
        $mantissalow2 = ($rknumlow & 0x7fffffff);
        $value = $mantissa / pow(2, 20 - ($exp - 1023));
        if ($mantissalow1 != 0) {
            $value += 1 / pow(2, 21 - ($exp - 1023));
        }
        $value += $mantissalow2 / pow(2, 52 - ($exp - 1023));
        if ($sign) {
            $value = -1 * $value;
        }
        return  $value;
    }

    public function addcell($row, $col, $string, $raw = '')
    {
        $this->sheets[$this->sn]['maxrow'] = max($this->sheets[$this->sn]['maxrow'], $row + $this->_rowoffset);
        $this->sheets[$this->sn]['maxcol'] = max($this->sheets[$this->sn]['maxcol'], $col + $this->_coloffset);
        $this->sheets[$this->sn]['cells'][$row + $this->_rowoffset][$col + $this->_coloffset] = $string;
        if ($raw) {
            $this->sheets[$this->sn]['cellsInfo'][$row + $this->_rowoffset][$col + $this->_coloffset]['raw'] = $raw;
        }
        if (isset($this->rectype)) {
            $this->sheets[$this->sn]['cellsInfo'][$row + $this->_rowoffset][$col + $this->_coloffset]['type'] = $this->rectype;
        }
    }

    private function getIEEE754($rknum)
    {
        if (($rknum & 0x02) != 0) {
                $value = $rknum >> 2;
        } else {
// I got my info on IEEE754 encoding from
// http://research.microsoft.com/~hollasch/cgindex/coding/ieeefloat.html
// The RK format calls for using only the most significant 30 bits of the
// 64 bit floating point value. The other 34 bits are assumed to be 0
// So, we use the upper 30 bits of $rknum as follows...
            $sign = ($rknum & 0x80000000) >> 31;
            $exp = ($rknum & 0x7ff00000) >> 20;
            $mantissa = 0x100000 | ($rknum & 0x000ffffc);
            $value = $mantissa / pow(2, 20 - ($exp - 1023));
            if ($sign) {
                $value = -1 * $value;
            }
        }

        if (($rknum & 0x01) != 0) {
            $value /= 100;
        }
        return $value;
    }

    private function encodeUTF16($string)
    {
        $result = $string;
        if ($this->_defaultEncoding) {
            switch ($this->_encoderFunction) {
                case 'iconv':
                    $result = iconv('UTF-16LE', $this->_defaultEncoding, $string);
                    break;
                case 'mb_convert_encoding':
                    $result = mb_convert_encoding($string, $this->_defaultEncoding, 'UTF-16LE');
                    break;
            }
        }
        return $result;
    }

    private function getInt4d($data, $pos)
    {
        $value = ord($data[$pos]) | (ord($data[$pos + 1]) << 8) | (ord($data[$pos + 2]) << 16) | (ord($data[$pos + 3]) << 24);
        if ($value >= 4294967294) {
            $value = -2;
        }
        return $value;
    }
}
