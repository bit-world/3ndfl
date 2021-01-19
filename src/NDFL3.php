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
		foreach($currency_rates->Currency as $currency) {
			$code = (string)$currency['Code'];
			$this->code_name[$code] = (string)$currency['Name'];
			$tmp_rates = [];
			foreach($currency->TendersDateRate as $rate) {
				$date = (string)$rate['Date'];
				$tmp_rates[$date] = [
					doubleval(str_ireplace(',', '.', $rate['Rate'])),
					doubleval($rate['Quantity']),
				];
			}
			for($m = 1; $m <= 12; $m++) {
				for($d = 1; $d <= 31; $d++) {
					$date = sprintf('%02d.%02d.2020', $d, $m);
					if(isset($tmp_rates[$date])) {
						$last = $tmp_rates[$date];
					}
					$this->rates[$code][$date] = $last;
				}
			}
		}
		//
		$this->dc0 = file_get_contents($file);
		$this->dc0 = mb_convert_encoding($this->dc0, 'utf-8', 'windows-1251');
		$this->dc0 = explode('@', $this->dc0);
		if($now != -1) {
			$this->dc0[1] = 
				str_ireplace('44211', $this->to_exel_date($now), $this->dc0[1]);
		}
		$this->log("load `{$file}`");
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
				$array['other'][] = str_pad($r, $count);
			}
			//$array['other'][5] = date('d.m.Y', strtotime('1899-12-30 +' . $array['other'][5] . ' day'));
			//$array['other'][6] = date('d.m.Y', strtotime('1899-12-30 +' . $array['other'][6] . ' day'));
		}
		return $array;
	}
	
	public function from_exel_date($date) {
		//excel date to d.m.Y
		//43831 - 01.01.2020
		//43961 - 10.05.2020
		return date('d.m.Y', strtotime('1899-12-30 +' . $date . ' day'));
	}
	
	private function to_exel_date($date) {
		//d.m.Y to excel date
		//43831 - 01.01.2020
		//43961 - 10.05.2020
		$excel_start = new \DateTime('1899-12-30'); //+1
		$our_date = new \DateTime($date);
		$diff = $excel_start->diff($our_date);
		$diff = $diff->format('%r%a') + 1;
		return $diff;
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
			$tmp_date = $this->from_exel_date($base['date']);
			$exch = $this->get_rate($code, $tmp_date);
		}
		$base['exch_rate'] = $exch[0];
		$base['exch_base'] = $exch[1];
		//multiply
		$base['in_rub'] = $this->nalog_round($base['in_usd'] * $base['exch_rate'] / $base['exch_base']);
		$base['tax_rub'] = $this->nalog_round($base['tax_usd'] * $base['exch_rate'] / $base['exch_base']);
		//date
		if($to_excel) {
			$base['date'] = $this->to_exel_date($base['date']);
		}
		//
		$line = [
			'type' => 'CurrencyIncome',
			'i' => sprintf('%03d', $base['i']), //№
			'other' => [
				0 => 14,
				1 => 1010, //Дивиденды
				2 => 'Дивиденды',
				3 => $base['name'],
				4 => $base['country'],
				5 => $base['date'],
				6 => $base['date'],
				7 => 1, //auto/manual
				8 => $base['currency_code'],
				9 => $base['exch_rate'],
				10 => $base['exch_base'],
				11 => $base['exch_rate'],
				12 => $base['exch_base'],
				13 => $base['currency'],
				14 => $base['in_usd'],
				15 => $base['in_rub'],
				16 => $base['tax_usd'],
				17 => $base['tax_rub'],
				18 => 0,
				19 => 0,
				20 => 0,
				21 => 0,
				22 => '',
				23 => 0,
				24 => str_repeat(' ', 15),
			]
		];
		return $line;
	}
	
	private function str_writer($data) {
		$result = $data['type'] . $data['i'];
		foreach($data['other'] as $d) {
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
			'other' => [
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