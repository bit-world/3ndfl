<?php

namespace PDF;

class Parser
{
	
	private $pages, $page, $data;
	
	public function __construct($file) {
		$content = file_get_contents($file);
		
		$raw = new Raw();
		$this->data = $raw->parse($content); //, false
		//print_r($this->data);
		
		foreach($this->data as $id => $obj) {
			$opt = $obj['options'];
			$st = $obj['stream'];

			if(isset($opt['Type'])) {
				switch($opt['Type']) {
					case 'Pages':
						$this->pages = $opt['Kids'];
						break;
					case 'Page':
						$this->page[$id] = $opt['MediaBox'];
						$this->page[$id]['r'] = isset($opt['Rotate']) ? $opt['Rotate'] : 0;
						break;	
				}
			}
			
		}
		
	}
	
	public function getCount() {
		return sizeof($this->pages);
	}
	
	public function getPage($index, &$bounds) {
		$page_id = $this->pages[$index];
		$page = $this->data[$page_id];
		
		//print_r($page);
		
		$options = $page['options'];
		
		$media = new Media($this->page[$page_id]);
		
		
		if(isset($options['Resources'])) {
			
			if(is_array($options['Resources'])) {
				$res = $options['Resources'];
			} else {
				$res = isset($this->data[$options['Resources']]) ? $this->data[$options['Resources']] : [];
				$res = $res['options'];
				//print_r($res);
			}
			
			$fonts = isset($res['Font']) ? $res['Font'] : [];
			$fonts = $this->getFonts($fonts);
			
			$images = isset($res['XObject']) ? $res['XObject'] : [];
			$gd = new MyGD;
			if(DRAW) {
				$images = $gd->getImages($this->data, $images);			
			}
		}
		
		$content = $this->data[$options['Contents']]['stream'];
		
		$content = str_ireplace(')Tj', ') Tj', $content);
		$commands = explode("\n", $content);
		
		if(DRAW && !DEBUG) {
			$im = imagecreatetruecolor($media->w(), $media->h());
			$white = imagecolorallocate($im, 255, 255, 255);
			imagefilledrectangle($im, 0, 0, $media->w(), $media->h(), $white);
		} else {
			$im = null;
		}
		
		//table starts
		$stroke = $this->getBounds($gd, $media, $commands);
		$stroke = array_keys($stroke);
		sort($stroke);
		//print_r($stroke);
		$bounds[0]['r1'][1] = $stroke[3];
		$this->doCMD($im, $gd, $media, $fonts, $images, $commands, $bounds);
		
		if(DRAW && !DEBUG) {
			foreach($bounds[0] as $k => $v) {
				list($r, $g, $b) = sscanf($v[3], "%02x%02x%02x");
				$color = imagecolorallocate($im, $r, $g, $b);
				if($v[0] == 0) {
					imageline($im, 0, (int)$v[1], $media->w(), (int)$v[1], $color);
					imageline($im, 0, (int)$v[2], $media->w(), (int)$v[2], $color);
				} else {
					imageline($im, (int)$v[1], 0, (int)$v[1], $media->h(), $color);
					imageline($im, (int)$v[2], 0, (int)$v[2], $media->h(), $color);
				}
			}
			//
			header('Content-Type: image/png');
			imagepng($im);
			imagedestroy($im);
		}
		
		
		foreach($bounds[1]['stroke'] as $k => $v) {
			$bounds[2][] = $k;
		}
		sort($bounds[2]);
		$bounds[1]['stroke'] = $bounds[2];
		
		$result = $bounds[1];
		unset($bounds[1]);
		unset($bounds[2]);
		return $result;
		
	}
	
	private function getFonts($arr) {
		//print_r($arr);
		$fonts = [];
		foreach($arr as $id => $obj) {
			$content = $this->data[$obj];
			$opt = $content['options'];
			//print_r($opt);
			if(isset($opt['FontDescriptor'])) {
				$opt2 = $this->data[$opt['FontDescriptor']]['options'];
				$opt['FontDescriptor'] = $opt2;
				/*if(isset($opt2['FontFile2'])) {
					$content['FontFile2'] = $this->data[$opt2['FontFile2']];
					//print_r($content['FontFile2']['options']);
					$fnt = $content['FontFile2']['stream'];
				}*/
			}
			//print_r($opt);
			$fonts['/' . $id] = $opt;
			$fonts['/' . $id]['file'] = __DIR__ . '/../PFHighwaySansPro-Light.ttf';
		}
		return $fonts;
	}
	
	private function getBounds($gd, $media, $commands) {
		$stroke = [];
		$path = [];
		foreach($commands as $cmd0) {
			$p = explode(' ', $cmd0);
			$cmd = array_pop($p);
			if(end($p) != 'Do') {
				switch($cmd) {
					case 'm': //Вложенный набор данных (новые координаты x y)
						$path[$cmd] = $p;
						break;
					case 'l': //Линия из x1 y1 в новые x2 y2
						$path[$cmd][] = $p;
						break;
					case 'S':
						$gd->getStroke($media, $path, $stroke);
						break;
				}
			}
		}
		return $stroke;
	}
	
	private function doCMD($im, $gd, $media, $fonts, $xobjects, $commands, &$bounds) {
		if(DEBUG) {
			//print_r($commands);
		}
		
		//graphics states stack
		$ss = [];
		//current graphics state
		$cs = [
			'lineCap' => 0,
			'lineWidth' => 0,
			'dashArray' => [],
			'dashPhase' => 0,
			'ctm' => [0, 0, 0, 0, 0, 0],
		];
		//font state
		$BT = false;
		$bt0 = [
			'Tw' => 0,
			'Tc' => 0,
			'tx' => 0,
			'ty' => 0,
			'tm' => [0, 0, 0, 0, 0, 0],
			'font' => '',
		];
		//color state
		$color = [
			'rg' => [0, 0, 0],
			'RG' => [0, 0, 0],
			'g' => 0,
			'G' => 0,
		];
		//path draw data
		$path = [];
		foreach($commands as $cmd0) {
			$p = explode(' ', $cmd0);
			$cmd = array_pop($p);
			
			if(end($p) != 'Do') {
				switch($cmd) {
					case 'q': //Сохранить графическое состояние в стек
						array_push($ss, $cs);
						break;
					case 'J': //Установить lineCap
						$cs['lineCap'] = $p[0];
						break;
					case 'w': //Установить lineWidth
						$cs['lineWidth'] = $p[0];
						break;
					case 'd': //Тип прерывистой линии (no dash [] 0) 
						$cs['dashArray'] = $p[0]; //([] no dash) ([2 1] 2 on, 1 off ...)
						$cs['dashPhase'] = $p[1]; //0 без смещения, 1 смещение на 1 ед
						break;
					case 'cm': 	//CTM Текущая матрица трансформации a b c d e f
						$cs['ctm'] = $p;
						break;
					case 'Q': //Восстановить графическое состояние из стека
						$cs = array_pop($ss);
						break;
					case 'BT': //Начало текстового объекта
						$BT = true;
						break;
					case 'ET': //Конец текстового объекта
						$BT = false;
						break;
					case 'Td': //Аффинное преобразование - перемещение [tx, ty] * Tm
						$bt0['tx'] = $p[0];
						$bt0['ty'] = $p[1];
						break;
					case 'Tw': //Расстояние между словами (word spacing)
					case 'Tc': //Расстояние между символами (char spacing)
						$bt0[$cmd] = $p[0];
						break;
					case 'Tm': 	//Матрица трансформации 6 элементов
								//a b 0
								//c d 0
								//e f 1
						$bt0['tm'] = $p;
						break;
					case 'Tf': //Установка шрифта
						$bt0['font'] = $p[0];
						break;
					case 'Tj': //Отображает текстовую строку
						$gd->doPlain($im, $media, $cs, $bt0, $color, $fonts, $p, $bounds);
						break;
					case 'rg': 	//Цвет RGB (non-stroking)
					case 'RG': 	//Цвет RGB (stroking)
								//3 элемента r g b между 0 и 1
						$color[$cmd] = $gd->doColor($im, $p[0] * 255, $p[1] * 255, $p[2] * 255);
						break;
					case 'g': //Цвет gray (non-stroking)
					case 'G': //Цвет gray (stroking)
						$color[$cmd] = $gd->doColor($im, $p[0] * 255, $p[0] * 255, $p[0] * 255);
						break;
					case 'm': //Вложенный набор данных (новые координаты x y)
						$path[$cmd] = $p;
						break;
					case 're':	//Добавить Rectangle в набор данных для отрисовки
								//x y width height в пространстве пользователя
					case 'l': //Линия из x1 y1 в новые x2 y2
						$path[$cmd][] = $p;
						break;
					case 'F': //Отрисовка fill nonzero? наборов данных
					case 'f':
						$gd->doFill($im, $media, $cs, $color, $path);
						break;
					case 'S':
						$gd->doStroke($im, $media, $cs, $color, $path, $bounds);
						break;
					case 'Do':
						$gd->doXObject($im, $media, $xobjects, $ss, $cs, $color, $p);
						break;
					case '':
						break;
					default:
						//print_r($cmd0);
						throw new \Exception('cmd: ' . $cmd . ' doesn\'t exist');
						break;
				}
			} else {
				//Do XObject
				$gd->doXObject($im, $media, $xobjects, $ss, $cs, $color, $p);
				//print_r($p);
			}
			
		}
		
	}
	
}















