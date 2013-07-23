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
		
		$query = $this->db->querySingle("
			SELECT id FROM object_store
			WHERE name = {$this->escape($name)}
			");
		
		if (is_null($query)) {
			$this->db->exec("
				INSERT INTO object_store (name)
				VALUES ({$this->db->escapeString($name)})
				");
			$this->storageId = $this->db->lastInsertRowID();
		} else {
			$this->storageId = $query;
		}
	}
	
	public function put($object, $key=null) {
		
		if (is_null($key)) {
			$sql = "INSERT INTO object_data 
			(object_store_id, data) VALUES ( 
				{$this->storageId},
				{$this->escape(json_encode($object))}
				)";
		} else {
			$sql = "INSERT INTO object_data 
			(object_store_id, key_value, data) VALUES ( 
				{$this->storageId},
				{$this->escape($key)},
				{$this->escape(json_encode($object))}
				)";
		}
		
		$this->db->exec($sql);
		$object->id = $this->db->lastInsertRowID();
		$object->key = $key;
		
		return $object->id;
	}
	
	public function set($object) {
		
	}
	
	public function get($key) {
		
	}
	
	public function del($key) {
		
	}
	
	public function fetch() {
		
	}
	
	public function clear() {
		
	}
	
	private function escape($string) {
		return "'".$this->db->escapeString($string)."'";
	}
}
