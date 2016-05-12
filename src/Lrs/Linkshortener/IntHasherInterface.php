<?php namespace Lrs\IntHasher;

interface IntHasherInterface {
	
	public function hash($int);
	
	public function unhash($string);
		
}
