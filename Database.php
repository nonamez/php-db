<?php

namespace NoNameZ;

use PDO;

class Database extends PDO
{
	private static $_instance = null;

	public static function getInstance(...$args) {
		if (is_null(self::$_instance)) {
			self::connect(...$args);
		}

		return self::$_instance;
	}

	public static function connect(...$args) {
		$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8', $args[0], $args[1]);

		self::$_instance = new self($dsn, $args[2], $args[3], [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']);

		self::$_instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		self::$_instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

		return self::$_instance;
	}
	
	private function _getVars($data)
	{
		$columns = '`' . implode('`, `', array_keys($data)) . '`';
		$values  = ':' . implode(', :', array_keys($data));

		return array_values(compact('columns', 'values'));
	}

	public function insert($table, $data) : integer
	{
		if (count($data) == 0) {
			return FALSE;
		}

		$columns = '`' . implode('`, `', array_keys($data)) . '`';
		$values  = ':' . implode(', :', array_keys($data));

		$statement = $this->prepare(sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $table, $columns, $values));

		foreach ($data as $column => $value) {
			$statement->bindValue(':' . $column, $value);
		}

		$statement->execute();

		return $this->lastInsertId();
	}

	public function insertOrGetId($table, $data, $only = []) : integer
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
}