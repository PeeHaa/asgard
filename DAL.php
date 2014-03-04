<?php
namespace Coxis\DB;

class DAL {
	public $db = null;
	public $tables = array();
	public $columns = array();
	public $where = array();
	public $offset = null;
	public $limit = null;
	public $orderBy = null;
	public $groupBy = null;
	public $joins = array();
	public $params = array();

	public $into = null;

	public $page = null;
	public $per_page = null;

	protected $rsc = null;

	function __construct($db, $tables=null) {
		$this->db = $db;
		$this->addFrom($tables);
	}

	public function getParameters() {
		return $this->params;
	}
	
	public function from($tables) {
		$this->tables = array();
		return $this->addFrom($tables);
	}
	
	public function into($table) {
		$this->into = $table;
		return $this;
	}

	public function addFrom($tables) {
		if(!$tables)
			return $this;

		$tables = explode(',', $tables);
		foreach($tables as $tablestr) {
			$tablestr = trim($tablestr);

			preg_match('/(.*?) ([a-z_][a-z0-9_]*)?$/i', $tablestr, $matches);
			if(isset($matches[2])) {
				$alias = $matches[2];
				$table = $matches[1];
			}
			else
				$alias = $table = $tablestr;

			if(isset($this->tables[$alias]))
				throw new \Exception('Table alias '.$alias.' is already used.');
			$this->tables[$alias] = $table;
		}
		
		return $this;
	}

	public function removeFrom($what) {
		foreach($this->tables as $alias=>$table) {
			if($alias === $what) {
				unset($this->tables[$alias]);
				break;
			}
		}

		return $this;
	}

	protected function join($type, $table, $conditions=null) {
		if(is_array($table)) {
			foreach($table as $_table=>$_conditions)
				$this->leftjoin($_table, $_conditions);
			return $this;
		}
		$table_alias = explode(' ', $table);
		$table = $table_alias[0];
		if(isset($table_alias[1]))
			$alias = $table_alias[1];
		else
			$alias = $table;
		$this->joins[$alias] = array($type, $table, $conditions);
		return $this;
	}

	public function leftjoin($table, $conditions=null) {
		return $this->join('leftjoin', $table, $conditions);
	}

	public function rightjoin($table, $conditions=null) {
		return $this->join('rightjoin', $table, $conditions);
	}

	public function innerjoin($table, $conditions=null) {
		return $this->join('innerjoin', $table, $conditions);
	}

	// public function rsc() {
	// 	$query = $this->buildSQL();
	// 	return $this->query($query[0], $query[1]);
	// }

	// public function rsc() {
	// 	$query = $this->buildSQL();
	// 	$params = $this->getParameters();
	// 	// d($query);
	// 	return $this->query($query, $params)->rsc();
	// }

	public function next() {
		if($this->rsc === null)
			$this->rsc = $this->query();
		return $this->rsc->next();
	}
	
	public function reset() {
		$this->columns = null;
		$this->tables = null;
		$this->where = array();
		$this->offset = null;
		$this->limit = null;
		$this->orderBy = null;
		$this->groupBy = null;
		$this->joins = array();
		
		return $this;
	}
	
	public function query($sql=null, $params=array()) {
		if($sql === null) {
			$sql = $this->buildSQL();
			$params = $this->getParameters();
			return $this->query($sql, $params);
		}

		return $this->db->query($sql, $params);
	}
	
	/* GETTERS */
	public function first() {
		return $this->query()->first();
	}
	
	public function get() {
		return $this->query()->all();
	}
	
	public function paginate($page, $per_page=10) {
		$this->page = $page = $page ? $page:1;
		$this->per_page = $per_page;
		$this->offset(($page-1)*$per_page);
		$this->limit($per_page);
		
		return $this;
	}

	public function getPaginator() {
		if($this->page === null || $this->per_page === null)
			return;
		return new \Coxis\Utils\Paginator($this->count(), $this->page, $this->per_page);
	}

	/* SETTERS */
	#todo ajouter test pour $columns en string ou array
		#et verifier si necessaire pour les autres fonctions
	public function select($columns) {
		$this->columns = array();
		return $this->addSelect($columns);
	}

	public function addSelect($columns) {
		if(is_array($columns))
			return $this->_addSelect($columns);

		$columns = explode(',', $columns);
		foreach($columns as $columnstr) {
			$columnstr = trim($columnstr);

			preg_match('/(.*?) ([a-z_][a-z0-9_]*)?$/i', $columnstr, $matches);
			if(isset($matches[2])) {
				$alias = $matches[2];
				$column = $matches[1];
			}
			else
				$alias = $column = $columnstr;

			if(isset($this->columns[$alias]))
				throw new \Exception('Column alias '.$alias.' is not already used.');
			$this->columns[$alias] = $column;
		}
		
		return $this;
	}

	public function _addSelect($columns) {
		if(array_values($columns) === $columns) {
			foreach($columns as $k=>$v) {
				unset($columns[$k]);
				$columns[$v] = $v;
			}
		}
		$this->columns = array_merge($this->columns, $columns);
		return $this;
	}

	public function removeSelect($what) {
		foreach($this->columns as $alias=>$column) {
			if($alias === $what) {
				unset($this->columns[$alias]);
				break;
			}
		}
		return $this;
	}

	public function offset($offset) {
		$this->offset = $offset;
		return $this;
	}
		
	public function limit($limit) {
		$this->limit = $limit;
		return $this;
	}

	public function orderBy($orderBy) {
		$this->orderBy = $orderBy;
		return $this;
	}
		
	public function groupBy($groupBy) {
		$this->groupBy = $groupBy;
		return $this;
	}
		
	public function where($conditions, $values=null) {
		// d($conditions);
		if(!$conditions)
			return $this;

		if($values !== null)
			$this->where[$conditions] = $values;
		else		
			$this->where[] = $conditions;
		
		return $this;
	}

	/* CONDITIONS PROCESSING */
	protected function processConditions($params, $condition = 'and', $brackets=false, $table=null) {
		if(sizeof($params) == 0)
			return array('', array());
		
		$string_conditions = '';
		
		if(!is_array($params)) {
			// d($params); #todo remove the block
			if($condition == 'and')
				return array($this->replace($params, $table), array());
				// return array($params, array());
				// d($params);
			else
				return array($this->replace($condition, $table), array());
		}

		$pdoparams = array();

		foreach($params as $key=>$value) {
			if(!is_array($value)) {
		// d($value);
				if(is_int($key))
					$string_conditions[] = $this->replace($value);
					// $string_conditions[] = $value;
				else {
					$res = $this->replace($key);
					if(static::isIdentifier($key))
						$res .= '=?';
					$string_conditions[] = $res;
					$pdoparams[] = $value;
				}
			}
			else {
				if(is_int($key)) {
					$r = $this->processConditions($value, 'and', false, $table);
					$string_conditions[] = $r[0];
					$pdoparams[] = $r[1];
				}
				else {
					$r = $this->processConditions($value, $key, sizeof($params) > 1, $table);
					$string_conditions[] = $r[0];
					$pdoparams[] = $r[1];
				}
			}
		}

		$result = implode(' '.strtoupper($condition).' ', $string_conditions);
		
		if($brackets)
			$result = '('.$result.')';
		
		return array($result, \Coxis\Utils\Tools::flateArray($pdoparams));
	}

	#todo revemo
	public function removeJointure($alias) {
		unset($this->joins[$alias]);
		return $this;
	}
	
	protected function replace($condition) {
		// d($condition);
		$condition = preg_replace_callback('/[a-z_][a-z0-9._]*(?![^\(]*\))/', function($matches) {
			// d($matches, $this->identifierQuotes($matches[0]));
			// return 'test';

			if(strpos($matches[0], '.')===false && count($this->joins) > 0 && count($this->tables)===1)
				$matches[0] = array_keys($this->tables)[0].'.'.$matches[0];

			return $this->identifierQuotes($matches[0]);
		}, $condition);
		// d($condition);

		// if(strpos($condition, '?') === false) {
		// 	if(preg_match('/^[a-zA-Z0-9_]+$/', $condition))
		// 		$condition = '`'.$condition.'` = ?';
		// 	else
		// 		$condition = $condition.' = ?';
		// }
		
		return $condition;
	}

	protected static function isIdentifier($str) {
		return preg_match('/^[a-z_][a-z0-9._]*$/', $str);
	}

	protected function identifierQuotes($str) {
		return preg_replace_callback('/[a-z_][a-z0-9._]*/', function($matches) {
			$res = array();
			foreach(explode('.', $matches[0]) as $substr)
				$res[] = '`'.$substr.'`';
			return implode('.', $res);
		}, $str);
	}
	
	/*protected static function parseConditions($conditions) {
		$res = array();

		if(is_array($conditions)) {
			foreach($conditions as $k=>$v)
				if(is_int($k))
					$res[] = static::parseConditions($v);
				else {
					$ar = array();
					$ar[$k] = static::parseConditions($v);
					$res[] = $ar;
				}
			return $res;
		}
		else
			return $conditions;
	}*/
	
	/* BUILDERS */
	protected function buildColumns() {
		$select = array();
		if(!$this->columns)
			return '*';
		else {
			foreach($this->columns as $alias=>$table) {
				if($alias !== $table) {
					if($this->isIdentifier($table))
						$select[] = $this->identifierQuotes($table).' AS '.$this->identifierQuotes($alias);
					else
						$select[] = $table.' AS '.$this->identifierQuotes($alias);
				}
				else {
					if($this->isIdentifier($table))
						$select[] = $this->identifierQuotes($table);
					else
						$select[] = $table;
				}
			}
		}
		return implode(', ', $select);
	}

	protected function getDefaultTable() {
		if(count($this->tables) === 1)
			return array_keys($this->tables)[0];
		else
			return null;
	}

	protected function buildWhere($default=null) {
		// d($this->where);
		$params = array();
		$r = $this->processConditions($this->where, 'and', false, $this->getDefaultTable());
		// d($r);
		if($r[0])
			return array(' WHERE '.$r[0], $r[1]);
		else
			return array('', array());
	}

	protected function buildGroupby() {
		if(!$this->groupBy)
			return;

		$res = array();

		foreach(explode(',', $this->groupBy) as $column) {
			if($this->isIdentifier(trim($column)))
				$res[] = $this->replace(trim($column));
			else
				$res[] = trim($column);
		}

		return ' GROUP BY '.implode(', ', $res);
	}

	protected function buildOrderby() {
		if(!$this->orderBy)
			return;

		$res = array();

		foreach(explode(',', $this->orderBy) as $orderbystr) {
			$orderbystr = trim($orderbystr);

			preg_match('/(.*?) (ASC|DESC)?$/i', $orderbystr, $matches);

			if(isset($matches[2])) {
				$direction = $matches[2];
				$column = $matches[1];

				if($this->isIdentifier($column))
					$res[] = $this->replace($column).' '.$direction;
				else
					$res[] = $column.' '.$direction;
			}
			else {
				$column = $columnstr;

				if($this->isIdentifier($column))
					$res[] = $this->replace($column);
				else
					$res[] = $column;
			}
		}

		return ' ORDER BY '.implode(', ', $res);
	}

	protected function buildJointures() {
		$params = array();
		$jointures = '';
		// if($this->joins)
		// d($this->joins);
		foreach($this->joins as $alias=>$jointure) {
			// d($jointure);
			$type = $jointure[0];
			// $table = array_keys($jointure[1])[0];
			// $conditions = array_values($jointure[1])[0];
			// $table = $jointure[1][0];
			// $conditions = $jointure[1][1];
			$table = $jointure[1];
			$conditions = $jointure[2];
			$alias = $alias !== $table ? $alias:null;
			$res = $this->buildJointure($type, $table, $conditions, $alias);
			$jointures .= $res[0];
			$params = array_merge($params, $res[1]);
		}
		return array($jointures, $params);
	}

	protected function buildJointure($type, $table, $conditions, $alias=null) {
		$params = array();
		$jointure = '';
		switch($type) {
			case 'leftjoin':
				$jointure = ' LEFT JOIN ';
				break;
			case 'rightjoin':
				$jointure = ' RIGHT JOIN ';
				break;
			case 'innerjoin':
				$jointure = ' INNER JOIN ';
				break;
		}
		// if(static::isIdentifier($table))
		// 	$table = $this->identifierQuotes($table);
		// $table = preg_replace_callback('/(^[a-z_][a-z0-9._]*)| ([a-z_][a-z0-9._]*$)/', function($matches) {
		#(...) a
		#actu
		#actu a
		if($alias !== null)
			$table = $table.' '.$alias;
		$table = preg_replace_callback('/(^[a-z_][a-z0-9._]*)| ([a-z_][a-z0-9._]*$)/', function($matches) {
				return $this->identifierQuotes($matches[0]);
		}, $table);
		// d($table);
		$jointure .= $table;
		if($conditions) {
			$r = $this->processConditions($conditions);
			$jointure .= ' ON '.$r[0];
			$params = array_merge($params, $r[1]);
		}
		return array($jointure, $params);
	}

	protected function buildLimit() {
		if(!$this->limit && !$this->offset)
			return '';

		$limit = ' LIMIT ';
		if($this->offset) {
			$limit .= $this->offset;
			if($this->limit)
				$limit .= ', '.$this->limit;
			else
				$limit .= ', 18446744073709551615';
		}
		else
			$limit .= $this->limit;
		return $limit;
	}

	public function buildTables($with_alias=true) {
		$tables = array();
		if(!$this->tables)
			throw new \Exception('Must set tables with method from($tables) before running the query.');
		foreach($this->tables as $alias=>$table) {
			if($alias !== $table && $with_alias)
				$tables[] = '`'.$table.'` `'.$alias.'`';
			else
				$tables[] = '`'.$table.'`';
		}
		return implode(', ', $tables);
	}

	public function buildSQL() {
		$params = array();

		$tables = $this->buildTables();
		$columns = $this->buildColumns();
		$orderBy = $this->buildOrderBy();
		$limit = $this->buildLimit();
		$groupby = $this->buildGroupby();

		list($jointures, $joinparams) = $this->buildJointures();
		$params = array_merge($params, $joinparams);
		
		list($where, $whereparams) = $this->buildWhere();
		// d($where, $this->where);
		$params = array_merge($params, $whereparams);

		$this->params = $params;
		return 'SELECT '.$columns.' FROM '.$tables.$jointures.$where.$groupby.$orderBy.$limit;
		// return array('SELECT '.$select.' FROM '.$table.$jointures.$where.$groupby.$orderBy.$limit, $params);
	}

	public function buildUpdateSQL($values) {
		if(sizeof($values) == 0)
			throw new \Exception('Update values should not be empty.');
		$params = array();

		$tables = $this->buildTables();
		$orderBy = $this->buildOrderBy();
		$limit = $this->buildLimit();

		list($jointures, $joinparams) = $this->buildJointures();
		$params = array_merge($params, $joinparams);

		foreach($values as $k=>$v)
			$set[] = $this->replace($k).'=?';
		$str = ' SET '.implode(', ', $set);
		$params = array_merge($params, array_values($values));
		
		list($where, $whereparams) = $this->buildWhere();
		$params = array_merge($params, $whereparams);
		

		$this->params = $params;
		return 'UPDATE '.$tables.$jointures.$str.$where.$orderBy.$limit;
	}

	#todo verifier ce format en ligne:  DELETE `n` FROM `news` `n` left join `category` WHERE `id`>500000 ORDER BY `id` ASC LIMIT 5
	// public function buildDeleteSQL($del_tables=null) {
	public function buildDeleteSQL() {
		$params = array();

		$tables = $this->buildTables(false);
		$orderBy = $this->buildOrderBy();
		$limit = $this->buildLimit();

		// list($jointures, $joinparams) = $this->buildJointures();
		// $params = array_merge($params, $joinparams);
		
		list($where, $whereparams) = $this->buildWhere();
		$params = array_merge($params, $whereparams);

		$this->params = $params;
		// if($del_tables !== null)
		// 	return 'DELETE '.$del_tables.' FROM '.$tables.$jointures.$where.$orderBy.$limit;
		// else
			// return 'DELETE FROM '.$tables.$jointures.$where.$orderBy.$limit;
			return 'DELETE FROM '.$tables.$where.$orderBy.$limit;
	}

	#todo a verifier en ligne si la query insert est complete ici?
	public function buildInsertSQL($values) {
		if(sizeof($values) == 0)
			throw new \Exception('Insert values should not be empty.');
		if($this->into === null && count($this->tables) !== 1)
			throw new \Exception('The into table is not defined.');
		if($this->into !== null)
			$into = $this->into;
		else
			$into = array_keys($this->tables)[0];

		$params = array();
		$into = $this->identifierQuotes($into);

		$cols = array();
		foreach($values as $k=>$v)
			$cols[] = $this->replace($k);
		$str = ' ('.implode(', ', $cols).') VALUES ('.implode(', ', array_fill(0, sizeof($values), '?')).')';
		$params = array_merge($params, array_values($values));
		
		$this->params = $params;
		return 'INSERT INTO '.$into.$str;
	}
	
	/* FUNCTIONS */
	public function update($values) {
		$sql = $this->buildUpdateSQL($values);
		$params = $this->getParameters();
		return $this->db->query($sql, $params)->affected();
	}
	
	public function insert($values) {
		$sql = $this->buildInsertSQL($values);
		$params = $this->getParameters();
		return $this->db->query($sql, $params)->id();
	}
	
	public function delete($tables=null) {
		$sql = $this->buildDeleteSQL($tables);
		$params = $this->getParameters();
		return $this->db->query($sql, $params)->affected();
	}

	#todo use a dal clone instead of resetting everything
	protected function _function($fct, $what=null, $group_by=null) {
		if($what)
			$what = '`'.$what.'`';
		else
			$what = '*';

		if($group_by) {
			// $this->select($this->table.'.`'.$group_by.'` groupby', $fct.'('.$what.') '.$fct)
				// ->groupBy($this->table.'.`'.$group_by.'`')
			$this->select($group_by.' groupby, '.strtoupper($fct).'('.$what.') '.$fct)
				->groupBy($group_by)
				->offset(null)
				->orderBy(null)
				->limit(null);
			$res = array();
			foreach($this->get() as $v)
				$res[$v['groupby']] = $v[$fct];
			return $res;
		}
		else {
			$this->select($fct.'('.$what.') '.$fct)
				->groupBy(null)
				->offset(null)
				->orderBy(null)
				->limit(null);
			return \Coxis\Utils\Tools::array_get($this->first(), $fct);
		}
	}
	
	public function count($group_by=null) {
		return $this->_function('count', null, $group_by);
	}
	
	public function min($what, $group_by=null) {
		return $this->_function('min', $what, $group_by);
	}
	
	public function max($what, $group_by=null) {
		return $this->_function('max', $what, $group_by);
	}
	
	public function avg($what, $group_by=null) {
		return $this->_function('avg', $what, $group_by);
	}
	
	public function sum($what, $group_by=null) {
		return $this->_function('sum', $what, $group_by);
	}
}
