<?php

// this interface defines the allowed storage operations
interface storage {

	// test if the $ver version of $key exists; returns true if it exists, false otherwise
	// if $ver is omitted, check if it exists at least a version
	public function has($key, $ver=false);

	// get the $ver version of key $id; returns false if it does not exists, the value otherwise
	// if $ver is omitted, return the latest version
	public function get($key, $ver=false);

	// put the key-value pair ($key, $value)
	// returns the newly created version on success, false otherwise
	public function put($key, $value);

	// delete all versions of $key; returns true on success, false otherwise
	public function rem($key);

	// list all available keys
	public function list();

	// get the creation timestamp of the $ver version of $key; returns the timestamp if it exists; false otherwise
	// if $ver is omitted, return the timestamp of the latest version
	public function time($key, $ver=false); 

}

class file_storage implements storage {

	private $dir;

	private function checkdir() {
		if (!$this->dir)
			throw new InvalidArgumentException("The directory '$dir' does not exist");
		if (!is_readable($this->dir))
			throw new InvalidArgumentException("The directory '$dir' is not readable");
		if (!is_writable($this->dir))
			throw new InvalidArgumentException("The directory '$dir' is not writable");
	}

	private function filename($key, $ver) {
		if (!is_integer($ver) || $ver < 0)
			$ver = false;
		if ($ver !== false)
			return file_exists("{$this->dir}/$key.$ver") ? realpath("{$this->dir}/$key.$ver") : false;
		return file_exists("{$this->dir}/$key") ? realpath("{$this->dir}/$key") : false;
	}

	public function has($key, $ver) {
		$this->checkdir();
		return $this->filename($key, $ver) !== false;
	}

	public function get($key, $ver) {
		$this->checkdir();
		$file = $this->filename($key, $ver);
		return $file ? file_get_contents($file) : false;
	}

	public function put($key, $value) {
		$this->checkdir();
		$file = $this->filename($key);
		$ver = $file ? intval(pathinfo($file, PATHINFO_EXTENSION)) + 1 : 0;
		file_put_contents("{$this->dir}/$key.$ver");
		unlink("{$this->dir}/$key!");
		link("{$this->dir}/$key.$ver", "{$this->dir}/$key!");
		return $ver;
	}

	public function rem($key) {
		$this->checkdir();
		return false;
	}

	public function list() {
		$this->checkdir();
		$files = scandir($this->dir, SCANDIR_SORT_NONE);
		$keys = array();
		foreach ($files as $file)
			if (substr($file, -1) == '!')
				$keys[] = substr($file, 0, -1);
		return $keys;
	}

	public function time($key, $ver) {
		$this->checkdir();
		$file = $this->filename($key);
		return $file ? filemtime($file) : false;
	}

	public function __construct($dir) {
		$this->dir = realpath($dir);
		$this->checkdir();
	}

}

class cached_file_storage extends file_storage {

	private $cache = array();

	private function cache_key($entry_type, $key, $ver) {
		if (!is_integer($ver) || $ver < 0)
			return "$entry_type.$key";
		return "$entry_type.$key.$ver";
	}
	
	public function has($key, $ver) {
		$cache_key = $this->cache_key('has', $key, $ver);
		if (!key_exists($this->cache, $cache_key))
			$this->cache[$cache_key] = parent::has($key, $ver);
		return $this->cache[$cache_key];
	}

	public function get($key, $ver) {
		$cache_key = $this->cache_key('get', $key, $ver);
		if (!key_exists($this->cache, $cache_key))
			$this->cache[$cache_key] = parent::get($key, $ver);
		return $this->cache[$cache_key];
	}

	public function put($key, $value) {
		$cache_key = $this->cache_key('get', $key);
		$this->cache[$cache_key] = $value;
		$ver = parent::put($key, $value);
		$cache_key = $this->cache_key('get', $key, $ver);
		$this->cache[$cache_key] = $value;
	}

	public function time($key, $ver) {
		$cache_key = $this->cache_key('time', $key, $ver);
		if (!key_exists($this->cache, $cache_key))
			$this->cache[$cache_key] = parent::time($key, $ver);
		return $this->cache[$cache_key];
	}
	
}

class sqlite_storage implements storage {

	private $db;
	private $q_has_any;
	private $q_has;
	private $q_get_latest;
	private $q_get;
	private $q_time_latest;
	private $q_time;

	public function has($key, $ver) {
		if (!is_integer($ver) || $ver < 0)
			$ver = false;
		if ($ver === false) {
			$this->q_has_any->bindValue(':key', $key, SQLITE3_TEXT);
			$r = $this->q_has_any->execute();
		} else {
			$this->q_has->bindValue(':key', $key, SQLITE3_TEXT);
			$this->q_has->bindValue(':ver', $ver, SQLITE3_INTEGER);
			$r = $this->q_has->execute();
		}		
		$count = $r->fetchArray(SQLITE3_NUM);
		return intval($count[0]) > 0;
	}

	public function get($key, $ver) {
		if (!is_integer($ver) || $ver < 0)
			$ver = false;
		if ($ver === false) {
			$this->q_get_latest->bindValue(':key', $key, SQLITE3_TEXT);
			$r = $this->q_get_latest->execute();
		} else {
			$this->q_get->bindValue(':key', $key, SQLITE3_TEXT);
			$this->q_get->bindValue(':ver', $ver, SQLITE3_INTEGER);
			$r = $this->q_get->execute();
		}		
		$count = $r->fetchArray(SQLITE3_NUM);
		return $count ? $r[0] : false;
	}

	public function put($key, $value) {
		$q_put = $this->db->prepare('INSERT INTO data(key, version, value) VALUES (:key, (SELECT MAX(version)+1 FROM data WHERE key=:key), :value)');
		$q_put->bindValue(':key', $key, SQLITE3_TEXT);
		$q_put->bindValue(':value', $value, SQLITE3_TEXT);
		$q_put->execute();
		$q_latest = $this->db->prepare('SELECT MAX(version) FROM data WHERE key=:key');
		$q_latest->bindValue(':key', $key, SQLITE3_TEXT);
		$r = $q_latest->execute();
		$ver = $r->fetchArray(SQLITE3_NUM);
		return $ver ? $ver[0] : false;
	}

	public function rem($key) {
		$q_rem = $this->db->prepare('DELETE FROM data WHERE key=:key');
		$q_rem->bindValue(':key', $key, SQLITE3_TEXT);
		return $q_rem->execute();
	}

	public function list() {
		$r = $this->db->query('SELECT DISTINCT(key) FROM data');
		$keys = array();
		while ($key = $r->fetchArray(SQLITE3_NUM))
			$keys[] = $key[0];
		return $keys;
	}

	public function time($key, $ver) {
		if (!is_integer($ver) || $ver < 0)
			$ver = false;
		if ($ver === false) {
			$this->q_time_latest->bindValue(':key', $key, SQLITE3_TEXT);
			$r = $this->q_time_latest->execute();
		} else {
			$this->q_time->bindValue(':key', $key, SQLITE3_TEXT);
			$this->q_time->bindValue(':ver', $ver, SQLITE3_INTEGER);
			$r = $this->q_time->execute();
		}		
		$time = $r->fetchArray(SQLITE3_NUM);
		return $time ? $r[0] : false;
	}

	public function __construct($db_file) {
		$this->db = new sqlite3($db_file);
		$this->db->exec('CREATE TABLE IF NOT EXISTS data (
			key TEXT NOT NULL, 
			version INTEGER NOT NULL, 
			value TEXT NOT NULL, 
			time INTEGER NOT NULL DEFAULT CURRENT_TIMESTAMP
			PRIMARY KEY(key, version)
		)');
		$this->q_has_any = $this->db->prepare('SELECT COUNT(*) FROM data WHERE key=:key');
		$this->q_has = $this->db->prepare('SELECT COUNT(*) FROM data WHERE key=:key AND version=:ver');
		$this->q_get_latest = $this->db->prepare('SELECT value FROM data WHERE key=:key ORDER BY version DESC LIMIT 1');
		$this->q_get = $this->db->prepare('SELECT value FROM data WHERE key=:key AND version=:ver LIMIT 1');
		$this->q_time_latest = $this->db->prepare('SELECT time FROM data WHERE key=:key ORDER BY version DESC LIMIT 1');
		$this->q_time = $this->db->prepare('SELECT time FROM data WHERE key=:key AND version=:ver LIMIT 1');
	}

}

?>
