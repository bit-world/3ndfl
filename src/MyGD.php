<?php

namespace PDF;

class MyGD
{
	
	public function getImages($im, $data, $arr) {		
		foreach($arr as $id => $obj) {
			$image = $data[$obj];
			//print_r($image);
			$options = $image['options'];
			$images[$id] = $this->imagefrombmpstring($image['stream'], $options['Width'], $options['Height']);
			$images[$obj] = &$images[$id];
			//
			//$t = $this->imagecreatefrombmpstring($images[$id]);
			//echo '<img src="data:image/jpg;base64,'. base64_encode($t) .'" />';
		}
		foreach($arr as $id => $obj) {
			$options = $data[$obj]['options'];
			if(isset($options['SMask'])) {
				$this->imagealphamask($images[$id], $images[$options['SMask']]);
			}

		}
		//print_r($images);
		
		return $images;
	}
	
	public function doPlain($im, $m, $cs, $bt0, $color, $fonts, $text) {
		$text = implode(' ', $text);
		$text = str_ireplace(['(', ')'], '', $text);
		//$text = mb_convert_encoding($text, 'utf-8', 'cp-1251');
		if(DEBUG) {
			print_r($bt0);
		}
		//print_r($bt0['font']);
		//print_r("\n---\n");
		
		for ($j = 0; $j < mb_strlen($text); $j++) {
			$c = $text[$j];
			$font = $fonts[$bt0['font']];
			
			$c = mb_convert_encoding($c, 'utf-8', 'cp-1251');
			imagefttext($im, 10, 0, $bt0['tm'][4] + $j * 12, $m->y($bt0['tm'][5]), $color['rg'], $font, $c);
		}
		
		
	}
	
	public function doFill($im, $m, $cs, $color, &$path) {
		if(DEBUG) {
			//print_r(['re' => $path['re']]);
			//$color['rg'] = imagecolorallocate($im, rand(0, 255), rand(0, 255), rand(0, 255));
		}
		foreach($path['re'] as $k => $p) {
			imagefilledrectangle($im,
				$m->z($p[0]), $m->y($p[1]),
				$m->z($p[0] + $p[2]), $m->y($p[1] + $p[3]),
				$color['rg']
			);
			unset($path['re'][$k]);
		}
	}
	
	public function doStroke($im, $m, $cs, $color, &$path) {
		if(DEBUG) {
			//print_r($path);
		}
		foreach($path['l'] as $k => $p) {
			imageline($im,
				$m->z($path['m'][0]), $m->y($path['m'][1]),
				$m->z($p[0]), $m->y($p[1]),
				$color['rg']
			);
			unset($path['l'][$k]);
		}
	}
	
	public function doXObject($im, $m, $xobjects, &$ss, &$cs, $color, $p) {
		if(DEBUG) {
			//print_r($p);
			//print_r($xobjects);
		}
		$img_id = mb_substr($p[8], 1);
		array_push($ss, $cs);
		$cs['ctm'] = array_slice($p, 1, 6);
		//Do
		//print_r($cs);
		$src = $xobjects[$img_id];
		imagecopyresampled(
			$im, $src, 
			$m->z($cs['ctm'][4]), $m->y($cs['ctm'][5]),
			0, 0,
			$m->z($cs['ctm'][0]), $m->z($cs['ctm'][3]),
			imagesx($src), imagesy($src)
		);
		//
		$cs = array_pop($ss);
	}
	
	public function imagefrombmpstring($imstr, $width = 800, $height = 300, $bits = 24, $key = '') {
		
		$im = imagecreatetruecolor($width, $height);
		imagealphablending($im, false);
		imagesavealpha($im, true);
		$white = imagecolorallocatealpha($im, 255, 255, 255, 127);
		imagefill($im, 0, 0, $white);

		$scan_line_size = (($bits * $width) + 7) >> 3; 
		$scan_line_align = ($scan_line_size & 0x03) ? 4 - ($scan_line_size & 0x03): 0; 
		for($i = 0, $l = $height - 1; $i < $height; $i++, $l--) { 
			$scan_line = substr($imstr, (($scan_line_size + $scan_line_align) * $l), $scan_line_size);
				$j = 0; $n = 0; 
				while($j < $scan_line_size) {
					$r = ord($scan_line{$j++});
					$g = ord($scan_line{$j++});
					$b = ord($scan_line{$j++});
					$col = imagecolorallocate($im, $r, $g, $b); 
					imagesetpixel($im, $n++, $height-$i, $col); 
				}  
		} 
		return $im;
		
	}
	
	
	public function imagecreatefrombmpstring($im) {
		ob_start();
		imagepng($im);
		$buffer = ob_get_clean();
		ob_end_clean();
		imagedestroy($im);
		//header('Content-Type: image/png'); 
		//imagepng($im, 'tmp.png'); 
		return $buffer;
	}
	
	
	private function imagealphamask( &$picture, $mask ) {
		// Get sizes and set up new picture
		$xSize = imagesx( $picture );
		$ySize = imagesy( $picture );
		$newPicture = imagecreatetruecolor( $xSize, $ySize );
		imagesavealpha( $newPicture, true );
		imagefill( $newPicture, 0, 0, imagecolorallocatealpha( $newPicture, 0, 0, 0, 127 ) );

		// Resize mask if necessary
		if( $xSize != imagesx( $mask ) || $ySize != imagesy( $mask ) ) {
			$tempPic = imagecreatetruecolor( $xSize, $ySize );
			imagecopyresampled( $tempPic, $mask, 0, 0, 0, 0, $xSize, $ySize, imagesx( $mask ), imagesy( $mask ) );
			imagedestroy( $mask );
			$mask = $tempPic;
		}

		// Perform pixel-based alpha map application
		for( $x = 0; $x < $xSize; $x++ ) {
			for( $y = 0; $y < $ySize; $y++ ) {
				$alpha = imagecolorsforindex( $mask, imagecolorat( $mask, $x, $y ) );
				$alpha = 127 - floor( $alpha[ 'red' ] / 2 );
				$color = imagecolorsforindex( $picture, imagecolorat( $picture, $x, $y ) );
				imagesetpixel( $newPicture, $x, $y, imagecolorallocatealpha( $newPicture, $color[ 'red' ], $color[ 'green' ], $color[ 'blue' ], $alpha ) );
			}
		}

		// Copy back to original picture
		imagedestroy( $picture );
		$picture = $newPicture;
	}
	
	private function plain($im, $index, $color, $font,
		$tm, $td, $tc, &$tx, &$ty,
		$font_id, $transformations, $text, $type) {

		if(MODE && TEXT) {
			echo 'x: ' . $tx . ', y: ' . $ty . ' ';
			echo $index . ' ' . $font_id . ' ' . $text . ' = ';
		}
		
		$isPlain = false;
		for ($j = 0; $j < strlen($text); $j++) {
			$c = $text[$j];
			if($c == '(') {
				$isPlain = true;
			} elseif($c == ')') {			
				$isPlain = false;
			} elseif($c == "\\") {
				$c2 = $text[$j + 1];
				$c3 = isset($text[$j + 2]) ? $text[$j + 2] : '';
				// \ или ( или )
				if (in_array($c2, array("\\", "(", ")"))) {
					$symbol = $c2;
				// \t \n etc
				} else {
					$symbol = $c2;
				}
				$j++;
			} else {
				if ($isPlain) {
					$symbol = mb_convert_encoding($c, 'utf-8', 'cp-1251');
					$plain[] = [$symbol, 6000, 500];
					//echo $symbol;
				}				
			}
		}
		if(MODE && TEXT) echo " \n ";
		
		$this->plain_draw($im, $tm, $td, $tc, $tx, $ty, $color, $font, $plain, $font_id, $transformations, $type);

	}
	
	
	function getSymbolByIndex($font_id, $transformations, $index, &$tracking) {
		$symbol = mb_convert_encoding(chr($index), 'utf-8', 'cp-1251') . ' ';
		
		$result = [$symbol, $width, $tracking];
		$tracking = '';
		return $result;
	}

	function plain_draw($im, $tm, $td, $tc, $x, $y,
		$color, $font, &$plain, $font_id,
		$transformations, $type) {
		
		global $mDATA;
		
		if(sizeof($plain) > 0) {
			

			$is_space = false;
			foreach($plain as $i => $tmp) {
				$symbol = $tmp[0];
				$width = $tmp[1]*($tm[0]/1000);
				$tracking = -(float)$tmp[2]*((float)$tm[0]/1000);
				if($tc[0] > 0 || $tc[0] < 0) {
					$spacing = $tc[0]*($tm[0]);
				}
				
				$xs = $x + $tracking;
				$xe = $xs + $width;
				
				if(MODE && TEXT) echo " `{$symbol}`" . " ";

				
				if($symbol != ' ') {

				} else {
					$x += 1.88;
				}
				
				if(!MODE) {
					$size = 10;
					imagefttext($im, $size, 0, $xs*2.3-100, -$y*2+1530, $color, $font, $symbol);
					
					if($is_space) {
						imagefttext($im, $size, 0, $xs*2.3-108, -$y*2+1530, $color, $font, '_');
					}
				}
				
				
				if($symbol != ' ') {
					//$x += ($width+$spacing);
					$x = $xe + $spacing;
				}
				
			}
			
	
			if(MODE && TEXT) echo "\n";
			
		}
		
		$plain = [];
		
	}
	
		

	/*
		
		
		
		if($options["Type"] == 'Font') {
			$font_id = '/' . $options["Name"];
			$transformations[$font_id]['Encoding'] = $options["Encoding"][0] . '_' . $options["Encoding"][1];
			$transformations[$font_id]['Widths'] = $options["Widths"];
		}
		
		$font_id = getCharTransformations($transformations, $data);
		
		foreach($transformations as $index => $val) {
			$Differences = $objects[$val['Encoding']]['Differences'];
			$shift = $Differences[0];
			for($j=1; $j<sizeof($Differences); $j++) {
				$transformations[$index]['Differences'][$shift+$j-1] = $Differences[$j];
			}
		}
		
		$texts = getTextUsingTransformations($page_size, $texts, $transformations, $type);
	*/


	function uchr($code) {
		return html_entity_decode('&#' . ((int)$code) . ';', ENT_QUOTES, 'UTF-8');
	}

	function getCharTransformations(&$transformations, $stream) {
		if(MODE) {
			//print_r($stream);
			//echo "\n\n\n";
		}
		if (preg_match_all('/CMapName\s(\/F[0-9]+)(.*?)endcmap/s', $stream, $matches)) {
		   foreach($matches[2] as $key=>$m1) {
			   $font_id = $matches[1][$key];
			   
				preg_match_all("#([0-9]+)\s+beginbfchar(.*)endbfchar#ismU", $m1, $chars, PREG_SET_ORDER);
				preg_match_all("#([0-9]+)\s+beginbfrange(.*)endbfrange#ismU", $m1, $ranges, PREG_SET_ORDER);

				for ($j = 0; $j < count($chars); $j++) {
					$count = $chars[$j][1];
					$current = explode("\n", trim($chars[$j][2]));
					// Читаем данные из каждой строчки.
					for ($k = 0; $k < $count && $k < count($current); $k++) {
						if (preg_match("#<([0-9a-f]{2,4})>\s+<([0-9a-f]{4,512})>#is", trim($current[$k]), $map))
							$transformations[$font_id][][str_pad($map[1], 4, "0")] = $map[2];
					}
				}

				for ($j = 0; $j < count($ranges); $j++) {

					$current = $ranges[$j][2];

					// Support for : <srcCode1> <srcCode2> <dstString>
					$regexp = '/<(?P<from>[0-9A-F]+)> *<(?P<to>[0-9A-F]+)> *<(?P<offset>[0-9A-F]+)>[ \r\n]+/is';
					preg_match_all($regexp, $current, $matches);
					//print_r($matches);
					foreach ($matches['from'] as $key => $from) {
						$char_from = hexdec($from);
						$char_to   = hexdec($matches['to'][$key]);
						$offset    = hexdec($matches['offset'][$key]);
						for ($char = $char_from; $char <= $char_to; $char++) {
							$transformations[$font_id][$char] = uchr($char - $char_from + $offset);
						}
					}
					// Support for : <srcCode1> <srcCodeN> [<dstString1> <dstString2> ... <dstStringN>]
					// Some PDF file has 2-byte Unicode values on new lines > added \r\n
					$regexp = '/<(?P<from>[0-9A-F]+)> *<(?P<to>[0-9A-F]+)> *\[(?P<strings>[\r\n<>0-9A-F ]+)\][ \r\n]+/is';
					preg_match_all($regexp, $current, $matches);
					//print_r($matches);
					foreach ($matches['from'] as $key => $from) {
						$char_from = hexdec($from);
						$strings   = array();
						preg_match_all('/<(?P<string>[0-9A-F]+)> */is', $matches['strings'][$key], $strings);
						foreach ($strings['string'] as $position => $string) {
							$parts = preg_split(
								'/([0-9A-F]{4})/i',
								$string,
								0,
								PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
							);
							$text  = '';
							foreach ($parts as $part) {
								$text .= uchr(hexdec($part));
							}
							$transformations[$font_id][$char_from + $position] = $text;
						}
					}
				}
		   }
		}
		
		return $font_id;
	}


	
}















