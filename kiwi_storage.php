<?php

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
		$cache_key = cache_key('has', $key, $ver);
		if (!key_exists($cache, $cache_key))
			$cache[$cache_key] = parent::has($key, $ver);
		return $cache[$cache_key];
	}

	public function get($key, $ver) {
		$cache_key = cache_key('get', $key, $ver);
		if (!key_exists($cache, $cache_key))
			$cache[$cache_key] = parent::get($key, $ver);
		return $cache[$cache_key];
	}

	public function put($key, $value) {
		$cache_key = cache_key('get', $key);
		$cache[$cache_key] = $value;
		$ver = parent::put($key, $value);
		$cache_key = cache_key('get', $key, $ver);
		$cache[$cache_key] = $value;
	}

	public function time($key, $ver) {
		$cache_key = cache_key('time', $key, $ver);
		if (!key_exists($cache, $cache_key))
			$cache[$cache_key] = parent::time($key, $ver);
		return $cache[$cache_key];
	}
	
}

?>
