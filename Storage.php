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
	private $database;
	
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
		$className   = get_class($object);
		
		if (is_null($key) == false) {
			// a key must be string or numeric
			if (	is_string($key)
				||	is_numeric($key)) {
				// do nothing
			} else {
				throw new \Exception("Invalid data type for \$key");
			}
		} elseif (isset( $objectClone->id)) {
			$key = $objectClone->id;
		} else {
			$key = uniqid();
		}
		
		unset($object->id);
		unset($objectClone->id);
		
		$sql = "INSERT INTO object_data 
		(object_store_id, key_value, class_name, data) VALUES ( 
			{$this->storageId},
			{$this->escape($key)},
			{$this->escape($className)},
			{$this->escape(json_encode($objectClone))}
			)";
		
		$query = $this->exec($sql);
		$object->id = $key;
		
		return $query;
	}
	
	public function set($object) {
		
		if (isset($object->id) == false) {
			throw new \Exception("\$object must have an id");
		}
		
		// get the key
		$objectClone = clone($object);
		
		$key = $objectClone->id;
		unset($objectClone->id);
		unset($objectClone->class_name);
		
		$sql = "
			UPDATE object_data
			SET data = {$this->escape(json_encode($objectClone))}
			WHERE object_store_id = {$this->storageId}
				AND key_value = {$this->escape($key)}
		";
		
		return $this->exec($sql);
	}
	
	public function get($key) {
		
		// build sql statement
		$sql = "SELECT * FROM object_data 
			WHERE object_store_id = {$this->storageId}
				AND key_value = {$this->escape($key)}
		";
		
		// get object		
		return $this->querySingle($sql, true);
	}
	
	public function del($object) {
		
		if (is_object($object) && isset($object->id)) {
			$key = $object->id;
		} else {
			throw new \Exception("Invalid data type for \$key");
		}
		
		$sql = "
			DELETE FROM object_data 
			WHERE object_store_id = {$this->storageId}
				AND key_value = {$this->escape($key)}
			";
		
		try {
			$this->exec($sql);
		} catch (\Exception $e) {
			throw $e;
		}
		
		return true;
	}
	
	/**
	 * @todo
	 **/
	
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
	 * 
	 * @return number of affected rows
	 **/
	
	public function clear() {
		
		$sql = "
			DELETE FROM object_data
			WHERE object_store_id = {$this->storageId}
			";
		
		return $this->exec($sql);
	}
	
	/**
	 * Returns a string that has been properly escaped
	 * 
	 * @return string
	 **/
	
	private function escape($string) {
		
		// dummy database instance
		$database = new SQLite3(':memory:');
		
		if (is_string($string)) {
			return "'".$database->escapeString($string)."'";
		} else {
			throw new \Exception(
				"Invalid data type for \$string. ".gettype($string)
				);
		}
	}
	
	/**
	 * @depreciated
	 **/
	
	private function recordToObject($record) {
		
		// fetch object properties from record
		$record['id'] = $record['key_value'];
		$class = $record['class_name'];
		unset($record['key_value']);
		unset($record['object_store_id']);
		
		$data = $record['data'];
		unset($record['data']);
		
		foreach (json_decode($data) as $name => $value) {
			$record[$name] = $value;
		}
		
		// initiate object
		if (is_string($class)) {
			if (in_array($class, get_declared_classes())) {
				$object = new $class;
			} else {
				throw new \Exception("Undefined class. $class");
			}
		} else {
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
	 * @return integer number of rows affected
	 **/
	
	private function exec($sql) {
		
		$this->open(SQLITE3_OPEN_READWRITE);
		
		// execute sql statement
		try {
			$query = $this->database->exec($sql);
		} catch (\Exception $e) {
			throw new \Exception($e->getMessage().' '.$sql);
		}
		
		$this->close();
		
		return $query;
	}
	
	/**
	 * Execute SQL statement and return the result
	 * 
	 * @param $sql The SQL query to execute.
	 * @param $entireRow By default, querySingle() returns 
	 *        the value of the first column returned by the query. 
	 *        If entire_row is TRUE, then it returns an object of 
	 *        the entire first row.
	 * @return object
	 **/
	
	private function querySingle($sql, $entireRow=false) {
		
		$this->open(SQLITE3_OPEN_READONLY);
		
		try {
			$query = $this->database->querySingle($sql, $entireRow);
		} catch (\Exception $e) {
			throw $e;
		}
		
		$this->close();
		
		if ($entireRow == false) {
			return $query;
		} else {
			$record = $query;
		}
		
		// fetch object properties from record
		$record['id'] = $record['key_value'];
		$class = $record['class_name'];
		unset($record['key_value']);
		unset($record['object_store_id']);
		unset($record['class_name']);
		
		$data = $record['data'];
		unset($record['data']);
		
		foreach (json_decode($data) as $name => $value) {
			$record[$name] = $value;
		}
		
		// initiate object
		if (is_string($class)) {
			if (in_array($class, get_declared_classes())) {
				$object = new $class;
			} else {
				throw new \Exception("Undefined class. $class");
			}
		} else {
			throw new \Exception("Invalid data type for \$class");
		}
		
		// assign properties to object
		foreach ($record as $name => $value) {
			$object->$name = $value;
		}
		
		return $object;
	}
	
	/**
	 * Open database instance and register storage
	 * 
	 * @return void
	 **/
	
	private function open($flags) {
		
		$file = $_ENV['STORAGE_DATABASE'];
		$storageName = $this->storageName;
		
		// create database instance
		$this->database = new SQLite3($file, $flags);
		
		// get storage id
		$query = $this->database->querySingle("
			SELECT id FROM object_store
			WHERE name = {$this->escape($storageName)}
			");
		
		if (is_null($query)) {
			$sql = "
				INSERT INTO object_store (name)
				VALUES ({$this->escape($storageName)})
				";
			$this->database->exec($sql);
			$this->storageId = $this->database->lastInsertRowID();
		} else {
			$this->storageId = $query;
		}
	}
	
	/**
	 * Close database instance
	 * 
	 * @return void
	 **/
	
	private function close() {
		$this->database->close();
		$this->database = null;
	}
}
