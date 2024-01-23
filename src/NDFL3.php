<?php

namespace PDF;

class NDFL3
{
	
	private $rates, $code_name;
	private $dc0, $last_i = 0;
	
	function __construct($file, $currency_file, $now = -1) {
		$currency_rates = simplexml_load_file($currency_file);
		$this->rates = [];
		$this->code_name = [];
		$y = date('Y') - 1;
		foreach($currency_rates->Currency as $currency) {
			$code = (string)$currency['Code'];
			$this->code_name[$code] = (string)$currency['Name'];
			$tmp_rates = [];
			$stop = false;
			foreach($currency->TendersDateRate as $rate) {
				$date = (string)$rate['Date'];
				$date_y = substr($date, -4);
				if($date_y == $y) 
					$stop = true;
				$tmp_rates[$date] = [
					doubleval(str_ireplace(',', '.', $rate['Rate'])),
					doubleval($rate['Quantity']),
				];
				if(!$stop) {
					$last = $tmp_rates[$date];
				}
			}			
			for($m = 1; $m <= 12; $m++) {
				for($d = 1; $d <= 31; $d++) {
					$date = sprintf('%02d.%02d.' . $y, $d, $m);
					if(isset($tmp_rates[$date])) {
						$last = $tmp_rates[$date];
					}
					$this->rates[$code][$date] = $last;
				}
			}
		}
		//print_r($this->rates);
		//
		$this->dc0 = file_get_contents($file);
		$this->dc0 = mb_convert_encoding($this->dc0, 'utf-8', 'windows-1251');
		$this->dc0 = explode('@', $this->dc0);
		if($now != -1) { //44569 - дата заполнения декларации
			$this->dc0[1] = 
				str_ireplace('45314', $this->to_excel_date($now), $this->dc0[1]);
		}
		$this->log("load `{$file}`");
	}
	
	private function dc_read($str) {
		list($type, $i) = sscanf($str, '%14s %04d');
		$str = mb_substr($str, 18);
		
		$i = 0;
		while(mb_strlen($str) > 0) {
			$i++;
			sscanf($str, '%4d', $len);
			$str = mb_substr($str, 4);
			$data = mb_substr($str, 0, $len);
			$str = mb_substr($str, $len);
			//var_dump($len);
			print_r($i . ') ' . $data . "\n");
		}
	}
	
	private function log($txt) {
		//echo $txt . "<br>\n";
	}
	
	private function get_rate($code, $date) {
		return $this->rates[$code][$date];
	}
	
	private function get_name($code) {
		return $this->code_name[$code];
	}
	
	public function nalog_round($in) {
		$out = round($in, 2);
		return $out;
	}
	
	private function str_get(&$str, $l) {
		$result = mb_substr($str, 0, $l);
		$str = mb_substr($str, $l);
		return $result;
	}
	
	public function get_dc0() {
		return $this->dc0;
	}
	
	public function str_reader($line, $type = 'CurrencyIncome') {
		$str = $this->dc0[$line];
		$array = [];
		if(strstr($str, $type) !== false) {
			$array['type'] = $this->str_get($str, mb_strlen($type));
			if($type == 'CurrencyIncome') {
				$array['i'] = $this->str_get($str, 3);
			}
			while(strlen($str) > 0) {
				$count = (int)$this->str_get($str, 4);
				$r = $this->str_get($str, $count);
				$array['info'][] = str_pad($r, $count);
			}
			//$array['info'][5] = date('d.m.Y', strtotime('1899-12-30 +' . $array['info'][5] . ' day'));
			//$array['info'][6] = date('d.m.Y', strtotime('1899-12-30 +' . $array['info'][6] . ' day'));
		}
		return $array;
	}
	
	public function from_excel_date($date) {
		//excel date to d.m.Y
		//43831 - 01.01.2020
		//43961 - 10.05.2020
		return date('d.m.Y', strtotime('1899-12-30 +' . $date . ' day'));
	}
	
	private function to_excel_date($date) {
		//d.m.Y to excel date
		//43831 - 01.01.2020
		//43961 - 10.05.2020
		$excel_start = new \DateTime('1899-12-30'); //+1
		$our_date = new \DateTime($date);
		$diff = $excel_start->diff($our_date);
		$diff = $diff->format('%r%a') + 1;
		return $diff;
	}
	
	public function to_rub($code, $date, $sum) {
		$exch = $this->get_rate($code, $date);
		return $this->nalog_round($sum * $exch[0] / $exch[1]);
	}
	
	public function gen_income($data, $to_excel) {
		$base = [
			'i' => 0,
			'date' => '01.01.2020',
			'name' => 'Дивиденды 1',
			'country' => '840',
			'currency_code' => '840',
			'in_usd' => 1.5,
			'tax_usd' => 0.5,
		];
		$base = array_replace($base, $data);
		//exchange rate
		$code = $base['currency_code'];
		$base['currency'] = $this->get_name($code);
		if($to_excel) {
			$exch = $this->get_rate($code, $base['date']);
		} else {
			$tmp_date = $this->from_excel_date($base['date']);
			$exch = $this->get_rate($code, $tmp_date);
		}
		$base['exch_rate'] = $exch[0];
		$base['exch_base'] = $exch[1];
		//multiply
		$base['in_rub'] = $this->nalog_round($base['in_usd'] * $base['exch_rate'] / $base['exch_base']);
		$base['tax_rub'] = $this->nalog_round($base['tax_usd'] * $base['exch_rate'] / $base['exch_base']);
		//date
		if($to_excel) {
			$base['date'] = $this->to_excel_date($base['date']);
		}
		//
		$line = [
			'type' => 'CurrencyIncome',
			'i' => sprintf('%04d', $base['i']),
			'info' => [
				0 => 0,
				1 => 1010, //Дивиденды
				2 => 'Дивиденды',
				3 => $base['name'],
				4 => $base['country'],
				5 => 643, //Россия
				6 => $base['date'],
				7 => $base['date'],
				8 => 1, //auto/manual
				9 => $base['currency_code'],
				10 => $base['exch_rate'],
				11 => $base['exch_base'],
				12 => $base['exch_rate'],
				13 => $base['exch_base'],
				14 => $base['currency'],
				15 => $base['in_usd'],
				16 => $base['in_rub'],
				17 => $base['tax_usd'],
				18 => $base['tax_rub'],
				19 => 0,
				20 => 0,
				21 => 0,
				22 => 0,
				23 => 0,
				24 => '',
				25 => 0,
				26 => str_repeat(' ', 15),
			]
		];
		return $line;
	}
	
	private function str_writer($data) {
		$result = $data['type'] . $data['i'];
		foreach($data['info'] as $d) {
			$result .= sprintf('%04d', mb_strlen($d));
			$result .= $d;
		}
		return trim($result);
	}
	
	public function append($data = [], $to_excel = true) {
		$data['i'] = $this->last_i;
		$data = $this->gen_income($data, $to_excel);
		$data = $this->str_writer($data);
		$this->dc0[] = $data;
		$this->last_i++;
		$this->log("new item {$this->last_i}");
	}
	
	public function save($file = false) {
		$this->dc0[10] = $this->str_writer([
			'type' => 'DeclForeign',
			'i' => '',
			'info' => [
				1 => $this->last_i,
				2 => str_repeat(' ', 18)
			]
		]);
		$tmp = implode('@', $this->dc0);
		$tmp = mb_convert_encoding($tmp, 'windows-1251', 'utf-8');
		if($file) {
			file_put_contents($file, $tmp);
		} else {
			return $tmp;
		}
		$this->log("saved");
	}
	
}