<?php namespace Lrs\Linkshortener;

use Lrs\IntHasher\IntHasherInterface;
use PDO;

/**
 *	Convert links to/from short hashes using MySQL.
 */
class LinkShortener {
	/**
	 *	Base URL of the shortening service
	 */
	protected $baseURL = '';
	/**
	 *	Instance of Lrs\IntHasher\IntHasherInterface
	 */
	protected $hasher;
	/**
	 *	Options
	 */
	protected $options = array();
	/**
	 *	Instance of PDO
	 */
	protected $pdo;
	public function __construct($baseURL, PDO $pdo, IntHasherInterface $hasher) {
		$this->baseURL = trim($baseURL, '/');
		$this->hasher = $hasher;
		$this->options = array(
			'db' => array(
				'id'			=> 'id',
				'referrals'		=> 'referrals',
				'table_name'	=> 'urls',
				'token'			=> 'token',
				'url'			=> 'url',
				'shortened'		=> 'shortened',
			),
		);
		$this->pdo = $pdo;
	}
	/**
	 *	Set or get the base URL for the shortening service
	 */
	public function baseURL($str = false) {
		if ( $str ) {
			$this->baseURL = trim($str, '/');
			return $this;	
		}
		return $this->baseURL;
	}
	public function find($link, $by = 'shortened', $return = 'row') {
		$sql = sprintf(
			"SELECT `%s`, `%s`, `%s` FROM `%s` WHERE `%s` = ?",
				$this->options['db']['id'],
				$this->options['db']['url'],
				$this->options['db']['shortened'],
				$this->options['db']['table_name'],
				$by == 'shortened' ? $this->options['db']['shortened'] : $this->options['db']['url']
		);
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute(array($link));
		if ( $return == 'count' ) {
			return $stmt->rowCount();
		} else if ( $return == 'url' ) {
			$results = $stmt->fetch(PDO::FETCH_OBJ);
			if ( $results ) {
				return $results->url;
			}
			return false;
		} else {
			return $stmt->fetch(PDO::FETCH_OBJ);
		}
	}
	/**
	 *	Track the number of times a link is accessed
	 */
	public function incrementReferralCounter($id) {
		$sql = sprintf(
			"UPDATE `%s` SET `%s` = `%s` + 1 WHERE `%s` = ?",
				$this->options['db']['table_name'],
				$this->options['db']['referrals'],
				$this->options['db']['referrals'],
				$this->options['db']['id']
		);
		$stmt = $this->pdo->prepare($sql);
		return $stmt->execute(array($id));
	}
	/**
	 *	Set or get options
	 */
	public function options($options = false) {
		if ( is_array($options) ) {
			$this->options = array_merge($this->options, $options);
			return $this;	
		}
		return $this->options;
	}
	/**
	 *	Store a link in MySQL and hash the resulting ID
	 */
	public function shorten($linkToShorten) {
		$linkToShorten = trim($linkToShorten);
		if ( $linkToShorten != '' && !is_numeric($linkToShorten) ) {
			$exists = $this->find($linkToShorten, 'url');
			if ( !$exists ) {
				$sql = sprintf(
					"INSERT INTO `%s` (`%s`, `%s`) VALUES (?, ?)",
						$this->options['db']['table_name'],
						$this->options['db']['url'],
						$this->options['db']['token']
				);
				$stmt = $this->pdo->prepare($sql);
				$stmt->execute(array(
					$linkToShorten,
					bin2hex(openssl_random_pseudo_bytes(16))
				));
				$id = $this->pdo->lastInsertId('id');
				$hash = $this->updateByNextAvailableHash($this->hasher->hash($id), $id);
				if ( $hash ) {
					return $this->url($hash);
				}
			} else {
				if ( $exists->shortened != '' ) {
					return $this->url($exists->shortened);
				}
				$id = $exists->id;
				return $this->url($this->hasher->hash($exists->id));
			}
		}
		return false;
	}
	public function shortenByCustom($linkToShorten, $shortened) {
		$linkToShorten = trim($linkToShorten);
		if ( $linkToShorten != '' ) {
			$exists = $this->find($linkToShorten, 'url');
			if ( !$exists ) {
				$exists = $this->find($shortened);
				if ( $exists ) {
					return 'Custom shortener already exists';
				}
				$sql = sprintf(
					"INSERT INTO `%s` (`%s`, `%s`, `%s`) VALUES (?, ?, ?)",
						$this->options['db']['table_name'],
						$this->options['db']['url'],
						$this->options['db']['shortened'],
						$this->options['db']['token']
				);
				$stmt = $this->pdo->prepare($sql);
				$stmt->execute(array(
					$linkToShorten,
					$shortened,
					bin2hex(openssl_random_pseudo_bytes(16))
				));
			} else {
				$shortened = $exists->shortened;
			}
			return $this->url($shortened);
		}
		return false;
	}
	/**
	 *	If auto-hashing by auto-incremented ID,
	 *	find the next available hash by first 
	 *	checking the auto-generated hash, and then
	 *	checking [auto-generated hash]+[a-z].
	 */
	public function updateByNextAvailableHash($hash, $id) {
		$array = range('a', 'z');
		array_unshift($array, $hash);
		$i = 1;
		foreach ( $array as $alpha ) {
			$possible = (!isset($possible) ? '' : $hash.'+').$alpha;
			$this->pdo->exec(sprintf('LOCK TABLES `%s` WRITE', $this->options['db']['table_name']));
			$sql = sprintf(
				'SELECT `%s` FROM `%s` WHERE `%s` = ?',
					$this->options['db']['id'],
					$this->options['db']['table_name'],
					$this->options['db']['shortened']	
			);
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute(array($possible));
			if ( $stmt->rowCount() == 0 ) {
				$sql = sprintf(
					"UPDATE `%s` SET `%s` = ? WHERE `%s` = %d",
						$this->options['db']['table_name'],
						$this->options['db']['shortened'],
						$this->options['db']['id'],
						$id
				);
				$stmt = $this->pdo->prepare($sql);
				$stmt->execute(array($possible));
				$this->pdo->exec('UNLOCK TABLES');
				return $possible;
			}
		}
		return false;
	}
	/**
	 *	Create a full URL to shortened link
	 */
	public function url($str) {
		return $this->baseURL().'/'.$str;	
	}
}
