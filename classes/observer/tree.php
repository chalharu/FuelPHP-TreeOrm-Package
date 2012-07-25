<?php
namespace TreeOrm;

class Observer_Tree extends \Orm\Observer
{
	public static $property = array( 'left' => 'lft', 'right' => 'rght', 'parent_id' => 'parent_id', 'id' => 'id');
	protected $_property;
	protected $_table;
	protected $_parentNode;

	public function __construct($class)
	{
		$props = $class::observers(get_class($this));
		$this->_property = isset($props['property']) && is_array($props['property']) ? array_merge($props['property'],static::$property) : static::$property ;
	}

	protected function getTable(\Orm\Model $obj)
	{
		$this->_table = call_user_func(get_class($obj).'::table');
		return $this->_table;
	}

	protected function getParentNode(\Orm\Model $obj)
	{
		$this->_parentNode = $this->getNode($obj, $obj->{$this->_property['parent_id']});
		return $this->_parentNode;
	}

	protected function getNode(\Orm\Model $obj, $id)
	{
		return \DB::select()->from($this->getTable($obj))->where($this->_property['id'], $id)->execute()->current();
	}

	protected function isTableEmpty(\Orm\Model $obj)
	{
		$nodeCount = $obj->query()->count();
		return ($nodeCount == 0);
	}

	protected function _createSql($type, $nodeValue = array())
	{
		$sql = '';
		$left		= \DB::quote_identifier($this->_property['left']);
		$right		= \DB::quote_identifier($this->_property['right']);
		$parent_id	= \DB::quote_identifier($this->_property['parent_id']);
		$table		= isset($nodeValue['table'])		? \DB::quote_table($nodeValue['table'])	: NULL;
		$parent_right	= isset($nodeValue['parent_right'])	? (int)$nodeValue['parent_right']	: NULL;
		$parent_left	= isset($nodeValue['parent_left'])	? (int)$nodeValue['parent_left']	: NULL;
		$original_left	= isset($nodeValue['original_left'])	? (int)$nodeValue['original_left']	: NULL;
		$original_right	= isset($nodeValue['original_right'])	? (int)$nodeValue['original_right']	: NULL;

		switch ($type) {
			case 'delete':
				if(!isset($original_right,$original_left,$table)){
					throw new \FuelException();
				}
				$sql = 'DELETE FROM ' . $table .
					' WHERE ' . $left . ' BETWEEN ' . $original_left . ' AND ' . $original_right .
					' AND NOT ' . $left . ' IN(' . $original_left . ',' . $original_right . ')';
				break;
			case 'insert':
				if(!isset($parent_right) || !isset($table)){
					throw new \FuelException();
				}
				$sql = 'UPDATE ' . $table . 
					' SET ' . $left . ' = CASE WHEN ' . $left . ' > ' . $parent_right .
					' THEN ' . $left . ' + 2' .
					' ELSE ' . $left . ' END,' .
					$right . ' = CASE WHEN ' . $right . ' >= ' . $parent_right .
					' THEN ' . $right . ' + 2' .
					' ELSE ' . $right . ' END' .
					' WHERE ' . $right  . ' >= ' . $parent_right;
				break;
			case 'update':
				if(!isset($original_right) || !isset($original_left) || !isset($parent_left) || !isset($parent_right) || !isset($table)){
					throw new \FuelException();
				}
				if( $parent_right < $original_left && $original_left < $original_right){
					$sql = 'UPDATE ' . $table . 
						' SET ' . $left . ' = CASE WHEN ' . $left .
						' BETWEEN ' . $original_left . ' AND ' . $original_right .
						' THEN ' . ($parent_right - $original_left) . ' + ' . $left .
						' WHEN ' . $left .
						' BETWEEN ' . $parent_right . ' AND ' . ($original_left - 1) .
						' THEN ' . ($original_right - $original_left + 1) . ' + ' . $left .
						' ELSE ' . $left . ' END, ' .
						$right . ' = CASE WHEN ' . $right .
						' BETWEEN ' . $original_left . ' AND ' . $original_right .
						' THEN ' . ($parent_right - $original_left) . ' + ' . $right .
						' WHEN ' . $right .
						' BETWEEN ' . $parent_right . ' AND ' . ($original_left - 1) .
						' THEN ' . ($original_right - $original_left + 1) . ' + ' . $right .
						' ELSE ' . $right . ' END ' .
						' WHERE ' . $left .
						' BETWEEN ' . $parent_left . ' AND ' . $original_right;
				} else if( $original_right < $parent_right && $original_left < $original_right){
					$sql = 'UPDATE ' . $table .
						' SET ' . $left . ' = CASE WHEN ' . $left .
						' BETWEEN ' . $original_left . ' AND ' . $original_right .
						' THEN ' . ($parent_right - $original_right - 1) . ' + ' . $left .
						' WHEN ' . $left .
						' BETWEEN ' . ($original_right + 1) . ' AND ' . ($parent_right - 1) .
						' THEN ' . ($original_left - $original_right - 1) . ' + ' . $left .
						' ELSE ' . $left . ' END, ' .
						$right . ' = CASE WHEN ' . $right .
						' BETWEEN ' . $original_left . ' AND ' . $original_right .
						' THEN ' . ($parent_right - $original_right  - 1) . ' + ' . $right .
						' WHEN ' . $right .
						' BETWEEN ' . ($original_right + 1) . ' AND ' . ($parent_right - 1) .
						' THEN ' . ($original_left - $original_right - 1) . ' + ' . $right .
						' ELSE ' . $right . ' END ' .
						' WHERE ' . $left .
						' BETWEEN ' . ($original_left < $parent_left ? $original_left : $parent_left) . ' AND ' . $parent_right;
				} else {
					throw new \FuelException();
				}
				break;
		}
		return $sql;
	}

	protected function _insert(\Orm\Model $obj)
	{
		if($obj->{$this->_property['parent_id']}){
			// parent_idがセットされている
			$parentNode = $this->getParentNode($obj);
			if(!$parentNode)
				throw new \FuelException('Parent id is not found.');
			$table = $this->getTable($obj);
			\DB::query($this->_createSql('insert',array('table' => $table, 'parent_right' => $parentNode{$this->_property['right']})))->execute();
			$obj->{$this->_property['left']} = $parentNode{$this->_property['right']};
			$obj->{$this->_property['right']} = $parentNode{$this->_property['right']} + 1;
		} else {
			// parent_idがセットされていない
			if(!$this->isTableEmpty($obj))
				throw new \FuelException();
			$obj->{$this->_property['left']} = 1;
			$obj->{$this->_property['right']} = 2;
			$obj->{$this->_property['parent_id']} = 0;
		}
	}

	protected function _update(\Orm\Model $obj)
	{
		if($obj->{$this->_property['parent_id']}){
			$table = $this->getTable($obj);
			$oldNode = $this->getNode($obj,$obj->{$this->_property['id']});

			if($oldNode{$this->_property['parent_id']} != $obj->{$this->_property['parent_id']}){
				$parentNode = $this->getParentNode($obj);
				if(!$parentNode)
					throw new \FuelException();
				\DB::query($this->_createSql('update',array('table' => $table, 'parent_right' => $parentNode{$this->_property['right']},
					 'parent_left' => $parentNode{$this->_property['left']}, 'original_right' => $oldNode{$this->_property['right']},
					 'original_left' => $oldNode{$this->_property['left']})))->execute();
				$oldNode = $this->getNode($obj,$obj->{$this->_property['id']});
			}
			$obj->{$this->_property['left']} = $oldNode{$this->_property['left']};
			$obj->{$this->_property['right']} = $oldNode{$this->_property['right']};
		}
	}

	public function before_save(\Orm\Model $obj)
	{
		if ($obj->is_new()){
			$this->_insert($obj);
		} else if ($obj->is_changed()){
			$this->_update($obj);
		}
	}

	public function before_delete(\Orm\Model $obj)
	{
		$table = $this->getTable($obj);
		\DB::query($this->_createSql('delete', array('table' => $table, 'original_right' => $obj->{$this->_property['right']},
			 'original_left' => $obj->{$this->_property['left']})))->execute();
	}
}

