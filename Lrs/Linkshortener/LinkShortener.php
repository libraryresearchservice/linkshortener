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
	/**
	 *	Get a link from MySQL by searching for the URL or ID
	 */
	public function getLink($link, $by = 'url', $return = 'rows') {
		$sql = sprintf(
			"SELECT `%s`, `%s` FROM `%s` WHERE `%s` = ?",
				$this->options['db']['id'],
				$this->options['db']['url'],
				$this->options['db']['table_name'],
				$by == 'url' ? $this->options['db']['url'] : $this->options['db']['id']
		);
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute(array($link));
		if ( $return == 'count' ) {
			return $stmt->rowCount();
		} else {
			return $stmt->fetch(PDO::FETCH_OBJ);
		}
	}
	/**
	 *	Get a link by converting a hash to ID
	 */
	public function getLinkByToken($hash) {
		$id = $this->hasher->unhash($hash);
		return $this->getLink($id, 'id');
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
	 *	Convert a hash to ID then get the related link
	 */
	public function lengthen($hash) {
		$link = $this->getLinkByToken($hash);
		if ( $link ) {
			$this->incrementReferralCounter($link->id);
			return $link->url;
		}
		return false;
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
		if ( $linkToShorten != '' ) {
			$exists = $this->getLink($linkToShorten);
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
			} else {
				$id = $exists->id;
			}
			return $this->url($this->hasher->hash($id));
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
