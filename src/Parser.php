<?php

namespace PDF;

use Smalot\PdfParser\PDFObject;

class Media
{
	
	private $media;
	const zoom = 1.5;
	
	public function __construct($media) {
		$this->media[0] = $media[0] * self::zoom;
		$this->media[1] = $media[1] * self::zoom;
		$this->media[2] = $media[2] * self::zoom;
		$this->media[3] = $media[3] * self::zoom;
	}
	
	public function z($z) {
		return $z * self::zoom;
	}
	
	public function w() {
		return $this->media[2];
	}
	
	public function h() {
		return $this->media[3];
	}
	
	public function x($x) {
		return $this->media[2] - $x * self::zoom;
	}
	
	public function y($y) {
		return $this->media[3] - $y * self::zoom;
	}
	
}

class Parser
{
	
	private $pages, $page, $data;
	
	public function __construct($file) {
		$content = file_get_contents($file, FILE_BINARY);
		
		$raw = new Raw();
		$this->data = $raw->parse($content); //, false
		
		
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
						break;	
				}
			}
			
		}
		
		//print_r($pages);
		//print_r($page);
	
		$page = $this->getPage(0);
		
		
		
		
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
				$content['FontDescriptor'] = $opt2;
				if(isset($opt2['FontFile2'])) {
					$content['FontFile2'] = $this->data[$opt2['FontFile2']];
					//print_r($content['FontFile2']['options']);
					$fnt = $content['FontFile2']['stream'];
					

					//print_r($fnt);
					//die();
					
				}
			}
			
			//print_r($content);
			
			
			$fonts['/' . $id] = __DIR__ . '/PFHighwaySansPro-Light.ttf';
			
			
			
		}
		return $fonts;
	}
	
	private function doCMD($im, $gd, $media, $fonts, $xobjects, $commands) {
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
		foreach($commands as $cmd) {
			$p = explode(' ', $cmd);
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
					case 'Tw': //Расстояние между слов (word spacing)
					case 'Tc': //Расстояние между слов (word spacing)
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
						$gd->doPlain($im, $media, $cs, $bt0, $color, $fonts, $p);
						break;
					case 'rg': 	//Цвет RGB (non-stroking)
					case 'RG': 	//Цвет RGB (stroking)
								//3 элемента r g b между 0 и 1
						$color[$cmd] = imagecolorallocate($im, $p[0] * 255, $p[1] * 255, $p[2] * 255);
						break;
					case 'g': //Цвет gray (non-stroking)
					case 'G': //Цвет gray (stroking)
						$color[$cmd] = imagecolorallocate($im, $p[0] * 255, $p[0] * 255, $p[0] * 255);
						break;
					case 'm': //Вложенный набор данных (новые координаты x y)
						$path[$cmd] = $p;
						break;
					case 're':	//Добавить Rectangle в набор данных для отрисовки
								//x y width height в пространстве пользователя
					case 'l': //Линия их x1 y1 в новые x2 y2
						$path[$cmd][] = $p;
						break;
					case 'F': //Отрисовка fill nonzero? наборв данных
					case 'f':
						$gd->doFill($im, $media, $cs, $color, $path);
						break;
					case 'S':
						$gd->doStroke($im, $media, $cs, $color, $path);
						break;
					case '':
						break;
					default:
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
		
	private function getPage($index) {
		$page_id = $this->pages[$index];
		$page = $this->data[$page_id];
		$options = $page['options'];
		$media = new Media($this->page[$page_id]);
		
		$fonts = $options['Resources']['Font'];
		$fonts = $this->getFonts($fonts);
		
		$gd = new MyGD;
		
		$images = $options['Resources']['XObject'];
		$images = $gd->getImages($im, $this->data, $images);
		
		$content = $this->data[$options['Contents']]['stream'];
		
		
		$content = str_ireplace(')Tj', ') Tj', $content);
		$commands = explode("\n", $content);
		
		$im = imagecreatetruecolor($media->w(), $media->h());
		$white = imagecolorallocate($im, 255, 255, 255);
		imagefilledrectangle($im, 0, 0, $media->w(), $media->h(), $white);
		
		
		$this->doCMD($im, $gd, $media, $fonts, $images, $commands);
		
		
		if(!DEBUG) {
			header('Content-Type: image/png');
			imagepng($im);
		}
		imagedestroy($im);
		
	}
	

	
	
}















