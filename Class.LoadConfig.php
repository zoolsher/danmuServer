<?php
namespace DanMu\Server;
use ArrayAccess;

class LoadConfig implements ArrayAccess {
	private $_ini_file = './config.ini';
	private $_ini = null;
	public static $_isParsed = false;
	function __construct() {
		if (!self::$_isParsed) {
			if (file_exists($this->_ini_file)) {
				$this->_ini = parse_ini_file($this->_ini_file, true);
			} else {
				throw new Exception("ini file not exists", 1);
			}
			self::$_isParsed = true;
		}
	}
	public function offsetSet($offset, $value) {
		$this->_ini[$offset] = $value;
	}

	public function offsetExists($offset) {
		return isset($this->_ini[$offset]);
	}

	public function offsetUnset($offset) {
		unset($this->_ini[$offset]);
	}

	public function offsetGet($offset) {
		return isset($this->_ini[$offset]) ? $this->_ini[$offset] : null;
	}
}