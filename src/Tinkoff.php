<?php

namespace PDF;

define('DEBUG', !true);
define('DRAW', !true);

class Tinkoff
{
	
	private $okv, $oksm;
	
	private function get_oksm($code) {
		$code = mb_strtoupper($code);
		if(isset($this->oksm[$code])) {
			return $this->oksm[$code];
		}
		return null;
	}
	
	private function get_okv($code) {
		$code = mb_strtoupper($code);
		return $this->okv[$code];
	}
	
	public function parse($file) {
		
		$oksm = simplexml_load_file('oksm.xml');
		$this->oksm = [];
		foreach($oksm->Country as $country) {
			$icode = (string)$country['Code'];
			$code = (string)$country;
			$this->oksm[$code] = $icode;
		}
		//print_r($this->oksm);
		
		$okv = simplexml_load_file('okv.xml');
		$this->okv = [];
		foreach($okv->Currency as $currency) {
			$icode = (string)$currency['Code'];
			$code = (string)$currency;
			$this->okv[$code] = $icode;
		}
		//print_r($this->okv);
		
		//Output.pdf
		$parser = new Parser($file);
		
		$bounds[0] = [
			'r1' => [0, -1, 880, 'ff0000'],
			'w1' => [1, 120, 210, '00ff00'],
			'w2' => [1, 247, 457, '00ff00'],
			'w3' => [1, 577, 675, '00ff00'],
			'w4' => [1, 675, 757, '00ff00'],
			'w5' => [1, 757, 832, '00ff00'],
			'w6' => [1, 915, 997, '00ff00'],
			'w7' => [1, 997, 1095, '00ff00'],
			'w8' => [1, 1095, 1170, '00ff00'],
			'w9' => [1, 1170, 1230, '00ff00'],
		];
		
		$count = $parser->getCount();
		
		if($count > 0) {
			
			for($i = 0; $i < $count; $i++) {
				$pages[$i] = $parser->getPage($i, $bounds);
				//break;
			}
			
			$base = [];
			
			foreach($pages as $page) {
				
				//print_r($page);
				
				if(isset($page['r1'])) {
					
					foreach($page['r1'] as $k => $v1) {
						$td[$k] = [];
						$w2 = '';
						$w2_i = 4;
						foreach($v1 as $v) {
							if(mb_strstr($v[2], 'Рыжиков') !== false) {
								break;
							}
							if($k == 'w2' || $k == 'w3') {
								$w2_m = $page['stroke'][$w2_i];
								if($v[1] >= $w2_m) {
									$td[$k][] = trim($w2);
									$w2 = '';
									$w2_i++;
								}
								$w2 .= $v[2] . ' ';
								//
								if($v == end($v1)) {
									$td[$k][] = trim($w2);
								}
							} else {
								$td[$k][] = $v[2];
							}
							
						}
					}
					
					//print_r($max_y);
					//print_r($td);
					
					$nalog_year = date('Y') - 1;
									
					if(isset($td['w9'])) {
						$count = is_countable($td['w1']) ? count($td['w1']) : 0;
						for($i = 0; $i < $count; $i++) {
							//$before_tax = floatval(str_ireplace(',', '.', $td['w6'][$i]));
							$tax_sum = floatval(str_ireplace(',', '.', $td['w7'][$i]));
							$after_tax = floatval(str_ireplace(',', '.', $td['w8'][$i]));
							$before_tax = $after_tax + $tax_sum;
							$year = (int)substr($td['w1'][$i], -4);
							if($nalog_year == $year) {
								$base[] = [
									$td['w1'][$i], //date
									$td['w2'][$i], //name
									$before_tax,
									$tax_sum,
									$this->get_okv($td['w9'][$i]), //currency_code
									$this->get_oksm($td['w3'][$i]), //country code
									$td['w1'][$i] . '_' . $td['w5'][$i],
									$td['w3'][$i] //country name
								];
							}
						}
					}
					
					unset($td);
					
				}
			
			}
			
			
			return $base;
			
		}
		//die();
		//print_r($base);
		
		return [];
		
	}
	
	
	
	
	
}















