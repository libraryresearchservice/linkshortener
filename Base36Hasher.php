<?php namespace Lrs\IntHasher;

class Base36Hasher implements IntHasherInterface {
	
	/**
	 *	Convert integer to Base 36
	 */
	public function hash($integer) {
		return base_convert($integer, 10, 36);
	}
	
	/**
	 *	Convert Base 36 to integer
	 */
	public function unhash($string) {
		return intval($string, 36);
	}
		
}
