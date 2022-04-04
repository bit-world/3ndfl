<?php

namespace PDF;

class Media
{
	
	private $media, $rotate;
	const zoom = 1.5;
	
	public function __construct($media) {
		$this->media[0] = $media[0] * self::zoom;
		$this->media[1] = $media[1] * self::zoom;
		$this->media[2] = $media[2] * self::zoom;
		$this->media[3] = $media[3] * self::zoom;
		if($media['r'] == 90) {
			$r = $this->media[2];
			$this->media[2] = $this->media[3];
			$this->media[3] = $r;
		}
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