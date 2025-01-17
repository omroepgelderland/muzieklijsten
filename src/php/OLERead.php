<?php

/**
 * @author Sander Otten <sotten@gld.nl>
 */

namespace muzieklijsten;

class OLERead
{
    private const NUM_BIG_BLOCK_DEPOT_BLOCKS_POS = 0x2c;
    private const SMALL_BLOCK_DEPOT_BLOCK_POS = 0x3c;
    private const ROOT_START_BLOCK_POS = 0x30;
    private const BIG_BLOCK_SIZE = 0x200;
    private const SMALL_BLOCK_SIZE = 0x40;
    private const EXTENSION_BLOCK_POS = 0x44;
    private const NUM_EXTENSION_BLOCK_POS = 0x48;
    private const PROPERTY_STORAGE_BLOCK_SIZE = 0x80;
    private const BIG_BLOCK_DEPOT_BLOCKS_POS = 0x4c;
    private const SMALL_BLOCK_THRESHOLD = 0x1000;
    private const SIZE_OF_NAME_POS = 0x40;
    private const TYPE_POS = 0x42;
    private const START_BLOCK_POS = 0x74;
    private const SIZE_POS = 0x78;
    private const IDENTIFIER_OLE = pack("CCCCCCCC", 0xd0, 0xcf, 0x11, 0xe0, 0xa1, 0xb1, 0x1a, 0xe1);

    public $data = '';
    public $error;
    public $numBigBlockDepotBlocks;
    public $sbdStartBlock;
    public $rootStartBlock;
    public $extensionBlock;
    public $numExtensionBlocks;
    public $bigBlockChain;
    public $smallBlockChain;
    public $entry;
    public $props;
    public $wrkbook;
    public $rootentry;

    public function read($sFileName)
    {
      // check if file exist and is readable (Darko Miljanovic)
        if (!is_readable($sFileName)) {
            $this->error = 1;
            return false;
        }

        $this->data = @file_get_contents($sFileName);
        if (!$this->data) {
            $this->error = 1;
            return false;
        }

        if (substr($this->data, 0, 8) != self::IDENTIFIER_OLE) {
            $this->error = 1;
            return false;
        }
        $this->numBigBlockDepotBlocks = self::GetInt4d($this->data, self::NUM_BIG_BLOCK_DEPOT_BLOCKS_POS);
        $this->sbdStartBlock = self::GetInt4d($this->data, self::SMALL_BLOCK_DEPOT_BLOCK_POS);
        $this->rootStartBlock = self::GetInt4d($this->data, self::ROOT_START_BLOCK_POS);
        $this->extensionBlock = self::GetInt4d($this->data, self::EXTENSION_BLOCK_POS);
        $this->numExtensionBlocks = self::GetInt4d($this->data, self::NUM_EXTENSION_BLOCK_POS);

        $bigBlockDepotBlocks = [];
        $pos = self::BIG_BLOCK_DEPOT_BLOCKS_POS;
        $bbdBlocks = $this->numBigBlockDepotBlocks;

        if ($this->numExtensionBlocks != 0) {
            $bbdBlocks = (self::BIG_BLOCK_SIZE - self::BIG_BLOCK_DEPOT_BLOCKS_POS) / 4;
        }

        for ($i = 0; $i < $bbdBlocks; $i++) {
              $bigBlockDepotBlocks[$i] = self::GetInt4d($this->data, $pos);
              $pos += 4;
        }


        for ($j = 0; $j < $this->numExtensionBlocks; $j++) {
            $pos = ($this->extensionBlock + 1) * self::BIG_BLOCK_SIZE;
            $blocksToRead = min($this->numBigBlockDepotBlocks - $bbdBlocks, self::BIG_BLOCK_SIZE / 4 - 1);

            for ($i = $bbdBlocks; $i < $bbdBlocks + $blocksToRead; $i++) {
                $bigBlockDepotBlocks[$i] = self::GetInt4d($this->data, $pos);
                $pos += 4;
            }

            $bbdBlocks += $blocksToRead;
            if ($bbdBlocks < $this->numBigBlockDepotBlocks) {
                $this->extensionBlock = self::GetInt4d($this->data, $pos);
            }
        }

        $pos = 0;
        $index = 0;
        $this->bigBlockChain = [];

        for ($i = 0; $i < $this->numBigBlockDepotBlocks; $i++) {
            $pos = ($bigBlockDepotBlocks[$i] + 1) * self::BIG_BLOCK_SIZE;

            for ($j = 0; $j < self::BIG_BLOCK_SIZE / 4; $j++) {
                $this->bigBlockChain[$index] = self::GetInt4d($this->data, $pos);
                $pos += 4;
                $index++;
            }
        }

        $pos = 0;
        $index = 0;
        $sbdBlock = $this->sbdStartBlock;
        $this->smallBlockChain = [];

        while ($sbdBlock != -2) {
            $pos = ($sbdBlock + 1) * self::BIG_BLOCK_SIZE;

            for ($j = 0; $j < self::BIG_BLOCK_SIZE / 4; $j++) {
                $this->smallBlockChain[$index] = self::GetInt4d($this->data, $pos);
                $pos += 4;
                $index++;
            }

            $sbdBlock = $this->bigBlockChain[$sbdBlock];
        }

        $block = $this->rootStartBlock;
        $pos = 0;
        $this->entry = $this->__readData($block);

        $this->__readPropertySets();
    }

    private function __readData($bl)
    {
        $block = $bl;
        $pos = 0;
        $data = '';

        while ($block != -2) {
            $pos = ($block + 1) * self::BIG_BLOCK_SIZE;
            $data = $data . substr($this->data, $pos, self::BIG_BLOCK_SIZE);
            $block = $this->bigBlockChain[$block];
        }
        return $data;
    }

    private function __readPropertySets()
    {
        $offset = 0;

        while ($offset < strlen($this->entry)) {
              $d = substr($this->entry, $offset, self::PROPERTY_STORAGE_BLOCK_SIZE);

              $nameSize = ord($d[self::SIZE_OF_NAME_POS]) | (ord($d[self::SIZE_OF_NAME_POS + 1]) << 8);

              $type = ord($d[self::TYPE_POS]);
              $startBlock = self::GetInt4d($d, self::START_BLOCK_POS);
              $size = self::GetInt4d($d, self::SIZE_POS);

            $name = '';
            for ($i = 0; $i < $nameSize; $i++) {
                $name .= $d[$i];
            }

            $name = str_replace("\x00", "", $name);

            $this->props[] = [
                'name' => $name,
                'type' => $type,
                'startBlock' => $startBlock,
                'size' => $size,
            ];

            if (($name == "Workbook") || ($name == "Book")) {
                $this->wrkbook = count($this->props) - 1;
            }

            if ($name == "Root Entry") {
                $this->rootentry = count($this->props) - 1;
            }

            $offset += self::PROPERTY_STORAGE_BLOCK_SIZE;
        }
    }

    public function getWorkBook()
    {
        if ($this->props[$this->wrkbook]['size'] < self::SMALL_BLOCK_THRESHOLD) {
            $rootdata = $this->__readData($this->props[$this->rootentry]['startBlock']);

            $streamData = '';
            $block = $this->props[$this->wrkbook]['startBlock'];
            $pos = 0;
            while ($block != -2) {
                  $pos = $block * self::SMALL_BLOCK_SIZE;
                $streamData .= substr($rootdata, $pos, self::SMALL_BLOCK_SIZE);

                $block = $this->smallBlockChain[$block];
            }

            return $streamData;
        } else {
            $numBlocks = $this->props[$this->wrkbook]['size'] / self::BIG_BLOCK_SIZE;
            if ($this->props[$this->wrkbook]['size'] % self::BIG_BLOCK_SIZE != 0) {
                $numBlocks++;
            }

            if ($numBlocks == 0) {
                return '';
            }

            $streamData = '';
            $block = $this->props[$this->wrkbook]['startBlock'];
            $pos = 0;
            //echo "block = $block";
            while ($block != -2) {
                $pos = ($block + 1) * self::BIG_BLOCK_SIZE;
                $streamData .= substr($this->data, $pos, self::BIG_BLOCK_SIZE);
                $block = $this->bigBlockChain[$block];
            }
            return $streamData;
        }
    }

    private static function GetInt4d($data, $pos)
    {
        $value = ord($data[$pos]) | (ord($data[$pos + 1])  << 8) | (ord($data[$pos + 2]) << 16) | (ord($data[$pos + 3]) << 24);
        if ($value >= 4294967294) {
            $value = -2;
        }
        return $value;
    }
}
