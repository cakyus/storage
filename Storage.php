<?php

/**
 * Simple data storage
 * 
 * Flags:
 *   SQLITE3_OPEN_READONLY
 *   SQLITE3_OPEN_READWRITE
 *   SQLITE3_OPEN_CREATE
 **/

class Storage {
	
	// database handler
	private $db;
	
	// storage name
	private $storageName;
	
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
		
		$this->storageName = $name;
		
		$this->db = new SQLite3(
			  $_ENV['STORAGE_DATABASE']
			, SQLITE3_OPEN_READWRITE
			);
			
		$query = $this->db->querySingle("
			SELECT id FROM object_store
			WHERE name = {$this->escape($name)}
			");
		
		if (is_null($query)) {
			$sql = "
				INSERT INTO object_store (name)
				VALUES ({$this->escape($name)})
				";
			$this->db->exec($sql);
			$this->storageId = $this->db->lastInsertRowID();
		} else {
			$this->storageId = $query;
		}
	}
	
	/**
	 * Add new object to storage
	 **/
	
	public function add($object, $key=null) {
		return $this->insert(false, $object, $key);
	}
	
	/**
	 * Save new object or replace existing object in storage
	 **/
	
	public function put($object, $key=null) {
		return $this->insert(true, $object, $key);
	}
	
	private function insert($replace=false, $object, $key) {
		
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
			$this->exec($sql);
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
		try {
			$query = $this->db->query($sql);
			$record = $query->fetchArray(SQLITE3_ASSOC);
		} catch (\Exception $e) {
			throw $e;
		}
		
		return $this->recordToObject($record, $class);
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
		
		if (empty($this->query)) {
			
			$sql = "
				SELECT * FROM object_data
				WHERE object_store_id = {$this->storageId}
			";
			
			try {
				$this->query = $this->db->query($sql);
			} catch (\Exception $e) {
				throw $e;
			}
		}
		
		
		try {
			$record = $this->query->fetchArray(SQLITE3_ASSOC);
		} catch (\Exception $e) {
			throw $e;
		}
		
		return $this->recordToObject($record);;
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
	
	/**
	 * Returns a string that has been properly escaped
	 * 
	 * @return string
	 **/
	
	private function escape($string) {
		
		$db = new SQLite3(':memory:');
		
		if (is_string($string)) {
			return "'".$db->escapeString($string)."'";
		} else {
			throw new \Exception(
				"Invalid data type for \$string. ".gettype($string)
				);
		}
	}
	
	private function recordToObject($record, $class=null) {
		
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
	
	// == LOW LEVEL FUNCTIONS ==
	/**
	 * Execute SQL statement that changes data or schema in database
	 * 
	 * @param $sql   sql statement
	 * @param $fetch retrieve record from the result
	 * 
	 * @return integer number of rows affected
	 **/
	
	private function exec(
		  $sql
		, $fetch=false
		) {
		
		// configure database
		$databasePath  = $_ENV['STORAGE_DATABASE'];
		
		if ($fetch) {
			$databaseFlags = SQLITE3_OPEN_READONLY;
		} else {
			$databaseFlags = SQLITE3_OPEN_READWRITE;
		}
		
		// configure storage
		$storageId     = null;
		$storageName   = $this->storageName;
		
		// open database
		$database = new SQLite3($databasePath, $databaseFlags);
		
		// get storage id
		$query = $database->querySingle("
			SELECT id FROM object_store
			WHERE name = {$this->escape($storageName)}
			");
		
		if (is_null($query)) {
			$sql = "
				INSERT INTO object_store (name)
				VALUES ({$this->escape($storageName)})
				";
			$database->exec($sql);
			$storageId = $database->lastInsertRowID();
		} else {
			$storageId = $query;
		}
		
		// execute sql statement
		try {
			$query = $database->exec($sql);
		} catch (\Exception $e) {
			throw new \Exception($e->getMessage().' '.$sql);
		}
		
		$database->close();
		unset($database);
		
		return $query;
	}
}
