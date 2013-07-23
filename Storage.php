<?php

/**
 * Simple data storage
 **/

class Storage {
	
	// database handler
	private $db;
	
	// storage id
	private $storageId;
	
	public function __construct($name) {
		
		// check library dependency
		if (class_exists('SQLite3') == false) {
			throw new \Exception('SQLite3 extension is required');
		}
		
		$this->db = new SQLite3(
			 $_ENV['STORAGE_DATABASE']
			,SQLITE3_OPEN_READWRITE
			);
		
		$query = $this->db->querySingle(
			 ' SELECT id FROM object_store'
			.' WHERE name = "'.$this->db->escapeString($name).'"
			');
		
		if (is_null($query)) {
			$this->db->exec(
				 ' INSERT INTO object_store (name)'
				.' VALUES ("'.$this->db->escapeString($name).'")'
			);
			$this->storageId = $this->db->lastInsertRowID();
		} else {
			$this->storageId = $query;
		}
	}
	
	public function put($object, $key=null) {
		
	}
	
	public function set($object, $key) {
		
	}
	
	public function get($key) {
		
	}
	
	public function del($key) {
		
	}
	
	public function fetch() {
		
	}
	
	public function clear() {
		
	}
}
