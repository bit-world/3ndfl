<?php

namespace PDF;

use Smalot\PdfParser\RawData\RawDataParser;

class Raw
{
	
	private $debug_options;
		
	private function obj_sort($a, $b) {
		$a = explode('_', $a);
		$b = explode('_', $b);
		if($a[0] == $b[0]) {
			return $a[1] - $b[1];
		}
		return $a[0] - $b[0];
	}
		
	private function debug_parse($infile) {
		preg_match_all("#([0-9]+) ([0-9]+) obj(.*)endobj#ismU", $infile, $obj);
		foreach($obj[1] as $key => $i1) {
			$data = $obj[3][$key];
			$i2 = $obj[2][$key];
			$object = preg_replace("#stream(.*)endstream#ismU", "", $data);
			if(preg_match("#<<(.*?)>>#ismU", $object, $options)) {
				$result[$i1 . '_' . $i2] = $options[1];
			}
		}
		uksort($result, [$this, 'obj_sort']);
		return $result;
	}
	
	private function parseHeaderElement($type, $value)
    {
        switch ($type) {
            case '<<':
            case '>>':
                $header = $this->parseHeader($value);
                return $header;
            case 'numeric':
                return $value;
            case 'boolean':
                return $value;
            case 'null':
                return 'null';
            case '(':
                return $value;
            case '<':
                return $this->parseHeaderElement('(', ElementHexa::decode($value));
            case '/':
                return $value;
            case '[':
                $values = [];
                if (is_array($value)) {
                    foreach ($value as $sub_element) {
                        $sub_type = $sub_element[0];
                        $sub_value = $sub_element[1];
                        $values[] = $this->parseHeaderElement($sub_type, $sub_value);
                    }
                }
                return $values;
			case 'objref':
                return $value;
			default:
				 throw new \Exception('Invalid type: ' . $type);
        }
    }
	
	private function parseHeader($structure) {
        $elements = [];
        $count = count($structure);
        for ($position = 0; $position < $count; $position += 2) {
            $name = $structure[$position][1];
            $type = $structure[$position + 1][0];
            $value = $structure[$position + 1][1];
            $elements[$name] = $this->parseHeaderElement($type, $value);
        }
        return $elements;
    }
	
	
	private function parseObject($id, $structure, $add_stream) {
		
		$options = [];
		$stream = '';
		
		foreach ($structure as $position => $part) {
			switch ($part[0]) {
                case '[':
                    break;
                case '<<':
					$options = $this->parseHeader($part[1]);
                    break;
                case 'stream':
					if($add_stream) {
						$stream = isset($part[3][0]) ? $part[3][0] : $part[1];
					}
					break;
				default:
                    break;
			} 
		}
		/*
		echo $this->debug_options[$id] . "\n\n";
		print_r($options);
		echo "\n\n\n";
		*/
		return [
			'options' => $options,
			'stream' => $stream,
		];
	}
	
	public function parse($data, $stream = true) {
		
		//$this->debug_options = $this->debug_parse($data);
		//print_r($this->debug_options);
		
		$raw = new TCPDF_PARSER($data);
		list($this->xref, $this->objects) = $raw->getParsedData();
		
		foreach ($this->objects as $id => $structure) {
			$result[$id] = $this->parseObject($id, $structure, $stream);
			unset($this->objects[$id]);
		}
		
		//print_r($result);
		
		return $result;
		
		
		
		
	}
	
}