<?php

namespace PDF;

class MyGD
{
	
	public function getImages($data, $arr) {
		//print_r($arr);
		foreach($arr as $id => $obj) {
			$image = $data[$obj];
			$images[$id] = $this->imagefrombmpstring($image);
			$images[$obj] = &$images[$id];
			//$t = $this->imagecreatefrombmpstring($images[$id]);
			//echo '<img src="data:image/jpg;base64,'. base64_encode($t) .'" />';
		}
		foreach($arr as $id => $obj) {
			$options = $data[$obj]['options'];
			if(isset($options['SMask'])) {
				$this->imagealphamask($images[$id], $images[$options['SMask']]);
			}
		}
		return $images;
	}
	
	private function get_font_width($i, $font) {
		$LastChar = isset($font['LastChar']) ? $font['LastChar'] : 254;
		$Widths = isset($font['Widths']) ? $font['Widths'] : [];
		$s = sizeof($Widths) - 1;
		$index = $s - ($LastChar - $i);
		$w = isset($Widths[$index]) ? $Widths[$index] : -1;
		return $w;
	}
	
	public function doPlain($im, $m, $cs, &$bt0, $color, $fonts, $text, &$bounds) {
		$text = implode(' ', $text);
		$text = str_ireplace(['\\(', '\\)'], ['0xB01', '0xB02'], $text);
		$text = str_ireplace(['(', ')'], '', $text);
		$text = str_ireplace(['0xB01', '0xB02'], ['(', ')'], $text);
		$text_1251 = mb_convert_encoding($text, 'utf-8', 'cp-1251');
		if(DEBUG) {
			//print_r($fonts);
			//die();
		}
		
		$font = $fonts[$bt0['font']];		
		$font_size = 10;
		
		$tx = $m->z($bt0['tm'][4]);
		$ty = $m->y($bt0['tm'][5]);
		
		$bx = '';
		$by = '';
		foreach($bounds[0] as $k => $v) {
			if($v[0] == 1) {
				if($tx > $v[1] && $tx < $v[2]) {
					$bx = $k;
				}
			} else {
				if($ty > $v[1] && $ty < $v[2]) {
					$by = $k;
				}
			}
		}
		if($bx != '' && $by != '') {
			$bounds[1][$by][$bx][] = [$tx, $ty, $text_1251];
		}
		
		if(!DRAW || DEBUG) return;
		for ($j = 0; $j < mb_strlen($text_1251); $j++) {
			//cp-1251
			$c1 = mb_substr($text_1251, $j, 1);
			//utf-8
			$c2 = mb_substr($text, $j, 1);
			$d = ord($c2);
			$w = $this->get_font_width($d, $font);;
			if($w > 0) {
				//$sh = $w / (6 * $font_size);
			} else {
				//$sh = $font_size;
			}
			$box = imagettfbbox($font_size, 0, $font['file'], $c1);
			$sh = abs($box[0]) + abs($box[2]);
			if(DEBUG) {
				//echo $c1 . '_';
				//echo $sh . ' ';
			}
			imagefttext($im, $font_size, 0, $tx, $ty, $color['rg'], $font['file'], $c1);
			$tx += $sh;
		}		
	}
	
	public function doColor($im, $r, $g, $b) {
		if(!DRAW || DEBUG) return;
		return imagecolorallocate($im, $r, $g, $b);
	}
	
	public function doFill($im, $m, $cs, $color, &$path) {
		if(!DRAW || DEBUG) return;
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
	
	public function getStroke($m, &$path, &$bounds) {
		foreach($path['l'] as $k => $p) {
			$y1 = $m->y($path['m'][1]);
			$y2 = $m->y($p[1]);
			if($y1 == $y2) {
				$bounds[(int)$y1] = 1;
			}
			unset($path['l'][$k]);
		}
	}
	
	public function doStroke($im, $m, $cs, $color, &$path, &$bounds) {
		foreach($path['l'] as $k => $p) {
			$x1 = $m->z($path['m'][0]);
			$y1 = $m->y($path['m'][1]);
			$x2 = $m->z($p[0]);
			$y2 = $m->y($p[1]);
			if(DRAW) {
				imageline($im,
					$x1, $y1,
					$x2, $y2,
					$color['rg']
				);
			}
			//print_r($y1 . "\n");
			if($y1 == $y2) {
				$bounds[1]['stroke'][(int)$y1] = 1;
			}
			unset($path['l'][$k]);
		}
	}
	
	public function doXObject($im, $m, $xobjects, &$ss, &$cs, $color, $p) {
		if(DEBUG) {
			/*print_r($p);
			print_r($ss);
			print_r($cs);*/
		}
		if(!DRAW || DEBUG) return;
		
		
		if(sizeof($p) > 1) {
			$img_id = mb_substr($p[8], 1);
			$src = $xobjects[$img_id];
			
			array_push($ss, $cs);
			$cs['ctm'] = array_slice($p, 1, 6);
			
			imagecopyresampled(
				$im, $src, 
				$m->z($cs['ctm'][4]), $m->y($cs['ctm'][5]+$cs['ctm'][3]),
				0, 0,
				$m->z($cs['ctm'][0]), $m->z($cs['ctm'][3]),
				imagesx($src), imagesy($src)
			);
			
			$cs = array_pop($ss);
		} else {
			
			$img_id = mb_substr($p[0], 1);
			$src = $xobjects[$img_id];
			
			//imagepng($src, 'tmp.png');
			
			if(DEBUG) {
				/*print_r($cs);
				print_r([
					$m->z($cs['ctm'][4]), $m->y($cs['ctm'][5]),
					$m->z(abs($cs['ctm'][0])), $m->z(abs($cs['ctm'][3])),
				]);*/
			}
			
			imagecopyresampled(
				$im, $src, 
				$m->z($cs['ctm'][4]), $m->y($cs['ctm'][5]),
				0, 0,
				$m->z(abs($cs['ctm'][0])), $m->z(abs($cs['ctm'][3])),
				imagesx($src), imagesy($src)
			);
			
			//imagepng($im, 'tmp.png');
			
		}

		
	}
	
	public function imagefrombmpstring($image) {
		
		if(DEBUG) {
			//print_r($image);
		}
		
		$stream = $image['stream'];
		$options = $image['options'];
		$width = $options['Width'];
		$height = $options['Height'];
		
		$ColorSpace = is_array($options['ColorSpace']) ? $options['ColorSpace'][0] : $options['ColorSpace'];
		if($ColorSpace == 'CalRGB' || $ColorSpace == 'DeviceRGB') {
			$bits = 3;
		} elseif($ColorSpace == 'DeviceGray') {
			$bits = 1;
		}
		
		$im = imagecreatetruecolor($width, $height);
		$scan_line_size = $bits * $width;
		
		for($i = 0; $i < $height; $i++) {
			$l = $height - $i;
			$scan_line = substr($stream, (($scan_line_size) * $l), $scan_line_size);
			$j = 0; $n = 0; 
			while($j < $scan_line_size) {
				if($ColorSpace == 'CalRGB' || $ColorSpace == 'DeviceRGB') {
					
					$r = ord($scan_line[$j++] ?? 0);
					$g = ord($scan_line[$j++] ?? 0);
					$b = ord($scan_line[$j++] ?? 0);
					$col = imagecolorallocate($im, $r, $g, $b);
					
				} elseif($ColorSpace == 'DeviceGray') {
					
					$c = ord($scan_line[$j++] ?? 0);
					$col = imagecolorallocate($im, $c, $c, $c);
					
				}
				imagesetpixel($im, $n++, $l, $col);
				
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
	
	
}
