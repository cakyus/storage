<?php

/**
 * Simple data storage
 **/

class Storage {
	
	// database handler
	private $db;
	
	// storage id
	private $storageId;
	
	// query handler
	private $query;
	
	/**
	 * Open database and find storage id
	 **/
	
	public function __construct($name) {
		
		// check library dependency
		if (class_exists('SQLite3') == false) {
			throw new \Exception('SQLite3 extension is required');
		}
		
		// open database
		$this->db = new SQLite3(
			 $_ENV['STORAGE_DATABASE']
			,SQLITE3_OPEN_READWRITE
			);
		
		// get storage id
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
	
	/**
	 * Save new object in storage
	 **/
	
	public function put($object, $key=null) {
		
		$objectClone = clone($object);
		
		if (is_null($key) == false) {
			if (	is_string($key)
				||	is_numeric($key)) {
				// do nothing
			} else {
				throw new \Exception("Invalid data type for \$key");
			}
		} elseif (isset( $objectClone->id)) {
			$key = $objectClone->id;
			unset( $objectClone->id);			
		} else {
			$key = uniqid();
		}
		
		$sql = "INSERT INTO object_data 
		(object_store_id, key_value, data) VALUES ( 
			{$this->storageId},
			{$this->escape($key)},
			{$this->escape(json_encode($objectClone))}
			)";
		
		try {
			$this->db->exec($sql);
		} catch (\Exception $e) {
			throw $e;
		}
		
		$object->id = $key;
		
		return $object;
	}
	
	public function set($object) {
		
		if (isset($object->id) == false) {
			throw new \Exception("\$object must have an id");
		}
		
		// get the key
		$objectClone = clone($object);
		$key = $objectClone->id;
		unset($objectClone->id);
		
		$sql = "
			UPDATE object_data
			SET data = {$this->escape(json_encode($objectClone))}
			WHERE object_store_id = {$this->storageId}
				AND key_value = {$this->escape($key)}
		";
		
		try {
			$result = $this->db->exec($sql);
		} catch (\Exception $e) {
			throw $e;
		}
		
		return $result;
	}
	
	public function get($key, $class=null) {
		
		// build sql statement
		$sql = "SELECT * FROM object_data 
			WHERE object_store_id = {$this->storageId}
				AND key_value = {$this->escape($key)}
		";
		
		// retrive record
		$query = $this->db->query($sql);
		$record = $query->fetchArray(SQLITE3_ASSOC);
		
		// fetch object properties from record
		$record['id'] = $record['key_value'];
		unset($record['key_value']);
		unset($record['object_store_id']);
		
		$data = $record['data'];
		unset($record['data']);
		
		foreach (json_decode($data) as $name => $value) {
			$record[$name] = $value;
		}
		
		// initiate object
		if (is_null($class)) {
			$object = new stdClass;
		} elseif (is_string($class)) {
			if (in_array($class, get_declared_classes())) {
				$object = new $class;
			} else {
				throw new \Exception("Undeclared class. $class");
			}
		} elseif (is_object($class) == false) {
			throw new \Exception("Invalid data type for \$class");
		}
		
		// assign properties to object
		foreach ($record as $name => $value) {
			$object->$name = $value;
		}
		
		return $object;
	}
	
	public function del($key) {
		
		if (is_null($key)) {
			throw new \Exception("Invalid data type for \$key");
		} elseif (is_object($key) && isset($key->id)) {
			$key = $key->id;
		}
		
		$sql = "
			DELETE FROM object_data 
			WHERE object_store_id = {$this->storageId}
				AND key_value = {$this->escape($key)}
			";
		
		try {
			$this->db->exec($sql);
		} catch (\Exception $e) {
			throw $e;
		}
		
		return true;
	}
	
	public function fetch() {
		
	}
	
	/**
	 * Delete all objects from storage
	 **/
	
	public function clear() {
		
		$sql = "
			DELETE FROM object_data
			WHERE object_store_id = {$this->storageId}
			";
			
		try {
			$this->db->exec($sql);
		} catch (\Exception $e) {
			throw $e;
		}
		
		return true;
	}
	
	private function escape($string) {
		
		if (is_string($string)) {
			return "'".$this->db->escapeString($string)."'";
		} else {
			throw new \Exception(
				"Invalid data type for \$string. ".gettype($string)
				);
		}
	}
}
