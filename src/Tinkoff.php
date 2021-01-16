<?php

namespace PDF;

define('DEBUG', isset($_GET['q']));
define('DRAW', !true);

class Tinkoff
{
	
	
	public function parse($file) {
		
		//Output.pdf
		//out-inc-state-2020.pdf
		$parser = new Parser($file);
		
		$bounds[0] = [
			'r1' => [0, 425, 2000, 'ff0000'],
			'w1' => [1, 0, 130, '00ff00'],
			'w2' => [1, 130, 260, '00ff00'],
			'w3' => [1, 260, 395, '00ff00'],
			'w4' => [1, 395, 530, '00ff00'],
			'w5' => [1, 530, 860, '00ff00'],
		];
		
		$count = $parser->getCount();
		for($i = 0; $i < $count; $i++) {
			if($i == 1) {
				$bounds[0]['r1'] = [0, 0, 2000, 'ff0000'];
			}
			$pages[$i] = $parser->getPage($i, $bounds);
		}
		
		$base = [];
		
		foreach($pages as $page) {
				
			foreach($page['r1'] as $k => $v1) {
				$td[$k] = [];
				$w5 = '';
				foreach($v1 as $v) {
					if($k == 'w5') {
						$w5 .= $v[2] . ' ';
						if(mb_strstr($w5, 'шт.') !== false) {
							preg_match('/бумаге\s(.*)\.\sКоличество/', $w5, $m);
							//print_r($m);
							$td[$k][] = $m[1];
							$w5 = '';
						}
					} else {
						if(mb_strstr($v[2], 'Руководитель') !== false) {
							break;
						}
						$td[$k][] = $v[2];
					}
				}
			}
			unset($td['w4']);
			
			$count = count($td['w1']);
			for($i = 0; $i < $count; $i++) {
				$base[] = [
					$td['w1'][$i],
					$td['w5'][$i],
					str_ireplace(',', '.', $td['w2'][$i]),
					str_ireplace(',', '.', $td['w3'][$i]),
					'840',
					'840',
					$td['w1'][$i] . '_' . $td['w5'][$i]
				];
			}
			
		
		}
		
		//print_r($base);
		
		return $base;
		
	}
	
	
	
	
	
}















