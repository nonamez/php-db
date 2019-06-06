<?php

namespace NoNameZ;

use PDO;

class DB extends PDO
{
	private static $_instances = [];

	public static function getKey(...$args)
	{
		// instance name
		if (count($args) == 1) {
			return $args[0];
		}

		// if connection data exists the last param will be instance name
		if (array_key_exists(4, $args)) {
			return $args[4];
		}

		return 'main';
	}

	public static function getInstance(...$args) : PDO {
		$key = self::getKey(...$args);

		if (array_key_exists($key, self::$_instances)) {
			return self::$_instances[$key];
		}

		return self::connect(...$args);
	}

	public static function connect(...$args) : PDO {
		$key = self::getKey(...$args);

		$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8', $args[0], $args[1]);

		self::$_instances[$key] = new self($dsn, $args[2], $args[3], [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']);

		self::$_instances[$key]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		self::$_instances[$key]->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

		return self::$_instances[$key];
	}
	
	private function _getVars($data)
	{
		$columns = '`' . implode('`, `', array_keys($data)) . '`';
		$values  = ':' . implode(', :', array_keys($data));

		return array_values(compact('columns', 'values'));
	}

	// Simple clean for own purposes
	private function cleanString($string)
	{
		return preg_replace('/[^a-zA-Z0-9_-]/', '', $string);
	}

	public function insert($table, $data) : int
	{
		if (count($data) == 0) {
			return FALSE;
		}

		$keys = array_keys($data);
		$keys = array_map([$this, 'cleanString'], $keys);

		$columns = '`' . implode('`, `', $keys) . '`';
		$values  = ':' . implode(', :', $keys);

		$statement = $this->prepare(sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $this->cleanString($table), $columns, $values));

		foreach ($data as $column => $value) {
			$statement->bindValue(':' . $column, $value);
		}

		$statement->execute();

		return (int) $this->lastInsertId();
	}

	public function insertOrGetId($table, $data, $only = []) : int
	{
		if (count($data) == 0) {
			return FALSE;
		}

		if (is_array($only) == FALSE) {
			$only = [$only];
		}

		if (count($only) > 0) {
			$_data = array_intersect_key($data, array_flip($only));
		} else {
			$_data = $data;
		}

		$where_and = [];

		foreach ($_data as $key => $value) {
			$key = $this->cleanString($key);

			$where_and[] = sprintf('`%s` = :%s', $key, $key);
		}

		$where_and = implode(' AND ', $where_and);

		$query = sprintf('SELECT `id` as `aggregate` FROM %s WHERE %s', $table, $where_and);

		$statement = $this->prepare($query);

		foreach ($_data as $column => $value) {
			$statement->bindValue(':' . $column, $value);
		}

		unset($_data);

		$statement->execute();

		$id = $statement->fetchColumn();

		if ($id) {
			return (int) $id;
		} else {
			return $this->insert($table, $data);
		}
	}

	public function fetchOneAsObject($table, $params = [], $columns = [])
	{
		if (count($columns) > 0) {
			$columns = array_map([$this, 'cleanString'], $columns);
			$columns = '`' . implode('`, `', $columns) . '`';
		} else {
			$columns = '*';
		}

		$query = sprintf('SELECT %s FROM `%s`',  $columns, $this->cleanString($table));

		if (count($params)) {
			$_params = [];

			foreach ($params as $name => $param) {
				$name = $this->cleanString($name);

				$_params[] = sprintf('`%s` = :%s', $name, $name); 
			}

			$query = $query . ' WHERE ' . implode(' AND ', $_params);
		}

		$query = $query . ' LIMIT 1';

		$statement = $this->prepare($query);

		if (count($params)) {
			foreach ($params as $name => $param) {
				$statement->bindValue(':' . $name, $param);
			}
		}

		$statement->execute();

		return $statement->fetchObject();
	}
}