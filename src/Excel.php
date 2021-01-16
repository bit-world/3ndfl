<?php

namespace PDF;

class Excel implements \Iterator
{
	private $strings = [];
	private $xlsx_data = null;
	public $outlineLevel;
	private $position;
	public $dimension;
	
	function __construct($file) {
		$this->zip = new \ZipArchive;
		$this->position = 0;
		$this->open($file);
	}

	function __destruct() {
		$this->zip->close();
	}
	
	public function open($file) {
		if ($this->zip->open($file) === true) {
			$ss = $this->zip->getFromName('xl/sharedStrings.xml');
			$xml = simplexml_load_string($ss);
			$this->strings = [];
			foreach ($xml->children() as $item) {
				$this->strings[] = (string)$item->t;
			}
			return $this;
		}
		return null;
	}
	
	public function sheet($index) {
		$file = 'xl/worksheets/sheet' . $index . '.xml';
		if ($this->zip->locateName($file) !== false) {
			$sh = $this->zip->getFromName($file);
			$this->xlsx_data = simplexml_load_string($sh);
			$this->dimension = $this->xlsx_data->dimension['ref'];
			$this->dimension = $this->getDimension($this->dimension);
			return true;
		}
		return false;
	}
	
	public function rewind() {
		$this->position = 0;
	}
	
	public function getIndex($cell = 'A1') {
		if(preg_match( '/([A-Z]+)(\d+)/', $cell, $m )) {
			$col = $m[1];
			$row = $m[2];
			$colLen = strlen( $col );
			$index  = 0;
			for ( $i = $colLen - 1; $i >= 0; $i -- ) {
				$index += ( ord( $col[$i] ) - 64 ) * pow( 26, $colLen - $i - 1 );
			}
			return array( $index - 1, $row - 1 );
		}
		return array(-1,-1);
	}
	
	public function getDimension($ref) {
		if (strpos($ref, ':' ) !== false ) {
			$d = explode(':', $ref );
			$idx = $this->getIndex( $d[1] );
			return [$idx[0] + 1, $idx[1] + 1];
		}
		if ($ref !== '') {
			$index = $this->getIndex($ref);
			return [$index[0] + 1, $index[1] + 1];
		}
		return [0, 0];
	}

	public function current() {
		if($this->xlsx_data !== null) {
			$row = $this->xlsx_data->sheetData->row[$this->position];
			$attr = $row->attributes();
			$this->outlineLevel = isset($attr['outlineLevel']) ? (int)$attr['outlineLevel'] : 0;
			$v = [];
			foreach($row as $cell) {
				$i = $this->getIndex($cell['r']);
				if(($tmp = $this->get_val($cell)) !== null) {
					$v[$i[0]] = $tmp;
				}
			}
			return $v;
		}
	}

	public function key() {
		return $this->position;
	}

	public function next() {
		++$this->position;
	}

	public function valid() {
		return isset($this->xlsx_data->sheetData->row[$this->position]);
	}
	
	private function get_val($item) {
		$attr = $item->attributes();
		$value = isset($item->v) ? (string)$item->v : null;
		$dataType = isset($attr['t']) ? $attr['t'] : 'e';
		return $dataType == 's' ? $this->strings[$value] : $value;
	}
	
}















