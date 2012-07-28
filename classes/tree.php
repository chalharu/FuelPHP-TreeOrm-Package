<?php
namespace TreeOrm;

class Tree
{
	protected $obj;
	protected $_table;
	protected $_property;
	public static $property = array( 'left' => 'lft', 'right' => 'rght', 'parent_id' => 'parent_id', 'id' => 'id');
	
	public static function forge($model = null)
	{
		if(is_object($model)){
			$obj = $model;
		} else {
			if(!is_callable('\\' . $model . '::find'))
				return null;
			$obj = call_user_func('\\' . $model . '::find','first');
		}
		return new static($obj);
	}
	
	public function __construct(\Orm\Model $obj)
	{
		$this->obj = $obj;
		$this->_table = call_user_func(get_class($obj).'::table');
		$props = call_user_func(get_class($obj) . '::observers', 'TreeOrm\\Observer_Tree');
		$this->_property = isset($props['property']) && is_array($props['property']) ? array_merge(static::$property, $props['property']) : static::$property ;
	}
	
	protected function getTable()
	{
		return $this->_table;
	}
	
	public function getParentNode($id = null) {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		if (is_array($id)) {
			extract (array_merge(array($prop_id => null), $id));
		}
		if (empty ($id)) {
			$id = $this->obj->id;
		}
		
		$node = \DB::select($prop_parent_id)->from($this->getTable())->where($prop_id,$id)->execute()->current();

		if ($node) {
			$parent = \DB::select()->from($this->getTable())->where($prop_id,$node[$prop_parent_id])->execute()->current();
			return $parent;
		}
		return false;
	}

	public function children($id = null, $direct = false, $orderColumn = null, $orderDirection = null, $limit = null) {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		if (is_array($id)) {
			extract (array_merge(array($prop_id => null), $id));
		}
		if ($id === null) {
			$id = $this->obj->id;
		} elseif(!$id) {
			$id = null;
		}
		if (!$orderColumn) {
			$orderColumn = $prop_left;
		}
		if (!$orderDirection) {
			$orderDirection = 'ASC';
		}
		if ($direct) {
			if($id === null) {
				if($limit)
					return \DB::select()->from($this->getTable())->where($prop_parent_id,null)->or_where($prop_parent_id,0)
						->order_by($orderColumn, $orderDirection)->limit($limit)->execute();
				else
					return \DB::select()->from($this->getTable())->where($prop_parent_id,null)->or_where($prop_parent_id,0)
						->order_by($orderColumn, $orderDirection)->execute();
			}
			if($limit)
				return \DB::select()->from($this->getTable())->where($prop_parent_id,$id)->order_by($orderColumn, $orderDirection)->limit($limit)->execute();
			else
				return \DB::select()->from($this->getTable())->where($prop_parent_id,$id)->order_by($orderColumn, $orderDirection)->execute();
		}
		if($id === null) {
			if($limit)
				return \DB::select()->from($this->getTable())->order_by($orderColumn, $orderDirection)->limit($limit)->execute();
			else
				return \DB::select()->from($this->getTable())->order_by($orderColumn, $orderDirection)->execute();
		}
		$node = \DB::select($prop_id,$prop_left,$prop_right)->from($this->getTable())->where($prop_id,$id)->execute()->current();
		if(!$node) {
			return false;
		}
		if($limit)
			return \DB::select()->from($this->getTable())->where($prop_left, '>',$node[$prop_left])->where($prop_right, '<',$node[$prop_right])
				->order_by($orderColumn, $orderDirection)->limit($limit)->execute();
		else
			return \DB::select()->from($this->getTable())->where($prop_left, '>',$node[$prop_left])->where($prop_right, '<',$node[$prop_right])
				->order_by($orderColumn, $orderDirection)->execute();
	}
	
	public function childCount($id = null, $direct = false) {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		if (is_array($id)) {
			extract (array_merge(array($prop_id => null), $id));
		}
		if ($id === null) {
			$id = $this->obj->id;
		} elseif(!$id) {
			$id = null;
		}
		if ($direct) {
			if($id === null) {
				$result = \DB::select(\DB::expr('COUNT(' . $prop_id . ')'))->from($this->getTable())
					->where($prop_parent_id,null)->or_where($prop_parent_id,0)->execute()->current();
				return (int)$result['COUNT(' . $prop_id . ')'];
			}
			$result = \DB::select(\DB::expr('COUNT(' . $prop_id . ')'))->from($this->getTable())->where($prop_parent_id,$id)->execute()->current();
			return (int)$result['COUNT(' . $prop_id . ')'];
		}
		if($id === null) {
			$result = \DB::select(\DB::expr('COUNT(' . $prop_id . ')'))->from($this->getTable())->execute()->current();
			return (int)$result['COUNT(' . $prop_id . ')'];
		}
		$node = \DB::select($prop_id,$prop_left,$prop_right)->from($this->getTable())->where($prop_id,$id)->execute()->current();
		if(!$node) {
			return false;
		}
		$result = \DB::select(\DB::expr('COUNT(' . $prop_id . ')'))->from($this->getTable())->where($prop_left, '>',$node[$prop_left])->where($prop_right, '<',$node[$prop_right])
			->execute()->current();
		return (int)$result['COUNT(' . $prop_id . ')'];
	}

	public function getPath($id = null) {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		if (is_array($id)) {
			extract (array_merge(array($prop_id => null), $id));
		}
		if (empty ($id)) {
			$id = $this->obj->id;
		}

		$node = \DB::select($prop_id,$prop_left,$prop_right)->from($this->getTable())->where($prop_id,$id)->execute()->current();
		if(!$node) {
			return false;
		}
		return \DB::select()->from($this->getTable())->where($prop_left, '<=',$node[$prop_left])->where($prop_right, '>=',$node[$prop_right])
			->order_by($prop_left, 'asc')->execute();
	}
	
	/*
	 * - 'id' id of record to use as top node for reordering
	 * - 'field' Which field to use in reordering defaults to id
	 * - 'order' Direction to order either DESC or ASC (defaults to ASC)
	 */
	public function reorder($id = null, $field = null, $order = 'ASC') {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		if($field === null) {
			$field = $prop_id;
		}
		if (is_array($id)) {
			extract (array_merge(array($prop_id => null, 'field' => $prop_id, 'order' => 'ASC'), $id));
		}
		if ($id === null) {
			$id = $this->obj->id;
		}
		
		$nodes = $this->children($id, true, $field, $order);
		if ($nodes) {
			foreach ($nodes as $node) {
				$this->moveDown($node[$prop_id], true);
				$this->reorder($node[$prop_id], $field, $order);
			}
		}
	}

	public function change($id1 = null, $id2 = null) {
		if (!$id1) {
			return false;
		}
		if (!$id2) {
			return false;
		}
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		
		$node1 = \DB::select($prop_id,$prop_left,$prop_right)->from($this->getTable())->where($prop_id,$id1)->execute()->current();
		if(!$node1) {
			return false;
		}
		$node2 = \DB::select($prop_id,$prop_left,$prop_right)->from($this->getTable())->where($prop_id,$id2)->execute()->current();
		if(!$node2) {
			return false;
		}
		
		if($node1[$prop_right] < $node2[$prop_left]) {
			$lft1	= $node1[$prop_left];
			$rght1	= $node1[$prop_right];
			$lft2	= $node2[$prop_left];
			$rght2	= $node2[$prop_right];
		} else	if($node2[$prop_right] < $node1[$prop_left]) {
			$lft1	= $node2[$prop_left];
			$rght1	= $node2[$prop_right];
			$lft2	= $node1[$prop_left];
			$rght2	= $node1[$prop_right];
		} else {
			return false;
		}
		
		$sql = 'UPDATE ' . $this->getTable() .
			' SET ' . $prop_left . ' = CASE WHEN ' . $prop_left . ' BETWEEN ' . $lft1 . ' AND ' . $rght1 . ' THEN ' . ($rght2 - $rght1) . ' + ' . $prop_left .
			' WHEN ' . $prop_left . ' BETWEEN ' . $lft2 . ' AND ' . $rght2 . ' THEN ' . ($lft1 - $lft2) . ' + ' . $prop_left .
			(($rght1 + 1) > ($lft2 - 1) ? '' : 
				(' WHEN ' . $prop_left . ' BETWEEN ' . ($rght1  + 1) . ' AND ' . ($lft2 - 1) . ' THEN ' . ($lft1 + $rght2 - $rght1 - $lft2) . ' + ' . $prop_left )).
			' ELSE ' . $prop_left . ' END, ' . $prop_right . ' = CASE WHEN ' . $prop_right . ' BETWEEN ' . $lft1 . ' AND ' . $rght1 . ' THEN ' . ($rght2 - $rght1) . ' + ' . $prop_right .
			' WHEN ' . $prop_right . ' BETWEEN ' . $lft2 . ' AND ' . $rght2 . ' THEN ' . ($lft1 - $lft2) . ' + ' . $prop_right .
			(($rght1 + 1) > ($lft2 - 1) ? '' :
				(' WHEN ' . $prop_right . ' BETWEEN ' . ($rght1 + 1) . ' AND ' . ($lft2 - 1) . ' THEN ' . ($lft1 + $rght2 - $rght1 - $lft2) . ' + ' . $prop_right )) .
			' ELSE ' . $prop_right . ' END WHERE ' . $prop_left . ' BETWEEN ' . $lft1 . ' AND ' . $rght2;
 		\DB::query($sql)->execute();
		
	}

	public function moveDown($id = null, $number = 1) {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		if (is_array($id)) {
			extract (array_merge(array($prop_id => null), $id));
		}
		if (!$number) {
			return false;
		}
		if (empty ($id)) {
			$id = $this->obj->id;
		}
		
		$node = \DB::select($prop_id,$prop_left,$prop_right,$prop_parent_id)->from($this->getTable())->where($prop_id,$id)->execute()->current();
		if(!$node) {
			return false;
		}
		if(!$node[$prop_right] && ($node[$prop_parent_id] == null || $node[$prop_parent_id] == 0)) {
			$updateSet = array();
			$updateSet[$prop_left] = ($this->_getMin() ?: 2) - 1;
			$updateSet[$prop_right] = ($this->_getMax() ?: 1) + 1;
			\DB::update($this->getTable())->set($updateSet)->where($prop_id, $node[$prop_id])->execute();
			return false;
		}
		
		$parentNode = \DB::select($prop_id,$prop_left,$prop_right,$prop_parent_id)->from($this->getTable())->where($prop_id,$node[$prop_parent_id])->execute()->current();
		if(!$parentNode) {
			return false;
		}
		if(!$parentNode[$prop_right]) {
			$this->moveDown($parentNode[$prop_id], true);
			$parentNode = \DB::select($prop_id,$prop_left,$prop_right,$prop_parent_id)->from($this->getTable())->where($prop_id,$node[$prop_parent_id])->execute()->current();
		}
		if(!$node[$prop_right]){
			$this->_insertLeafSpace($parentNode[$prop_right]);
			$updateSet = array();
			$updateSet[$prop_left] = $parentNode[$prop_right];
			$updateSet[$prop_right] = $parentNode[$prop_right] + 1;
			\DB::update($this->getTable())->set($updateSet)->where($prop_id, $node[$prop_id])->execute();
			return false;
		}
		
		$moveToNode = \DB::select()->from($this->getTable())
			->where($prop_left,\DB::expr('(SELECT MIN(' . $prop_left . ') FROM ' . $this->getTable() . ' WHERE ' . $prop_left . ' > ' . $node[$prop_right] . ' AND ' . $prop_right . ' < ' . $parentNode[$prop_right] . ')'))
			->execute()->current();
		if(!$moveToNode) {
			return false;
		}

		$this->change($id, $moveToNode[$prop_id]);
		
		if (is_int($number)) {
			$number--;
		}
		if ($number) {
			$this->moveDown($id, $number);
		}
	}

	public function moveUp($id = null, $number = 1) {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		if (is_array($id)) {
			extract (array_merge(array($prop_id => null), $id));
		}
		if (!$number) {
			return false;
		}
		if (empty ($id)) {
			$id = $this->obj->id;
		}
		
		$node = \DB::select($prop_id,$prop_left,$prop_right,$prop_parent_id)->from($this->getTable())->where($prop_id,$id)->execute()->current();
		if(!$node) {
			return false;
		}
		if(!$node[$prop_left] && ($node[$prop_parent_id] == null || $node[$prop_parent_id] == 0)) {
			$updateSet = array();
			$updateSet[$prop_left] = ($this->_getMin() ?: 2) - 1;
			$updateSet[$prop_right] = ($this->_getMax() ?: 1) + 1;
			\DB::update($this->getTable())->set($updateSet)->where($prop_id, $node[$prop_id])->execute();
			return false;
		}
		
		$parentNode = \DB::select($prop_id,$prop_left,$prop_right,$prop_parent_id)->from($this->getTable())->where($prop_id,$node[$prop_parent_id])->execute()->current();
		if(!$parentNode) {
			return false;
		}
		if(!$parentNode[$prop_left]) {
			$this->moveUp($parentNode[$prop_id], true);
			$parentNode = \DB::select($prop_id,$prop_left,$prop_right,$prop_parent_id)->from($this->getTable())->where($prop_id,$node[$prop_parent_id])->execute()->current();
		}
		if(!$node[$prop_right]){
			$this->_insertLeafSpace($parentNode[$prop_left] + 1);
			$updateSet = array();
			$updateSet[$prop_left] = $parentNode[$prop_left] + 1;
			$updateSet[$prop_right] = $parentNode[$prop_left] + 2;
			\DB::update($this->getTable())->set($updateSet)->where($prop_id, $node[$prop_id])->execute();
			return false;
		}
		
		$moveToNode = \DB::select()->from($this->getTable())
			->where($prop_right,\DB::expr('(SELECT MAX(' . $prop_right . ') FROM ' . $this->getTable() . ' WHERE ' . $prop_right . ' < ' . $node[$prop_left] . ' AND ' . $prop_left . ' > ' . $parentNode[$prop_left] . ')'))
			->execute()->current();
		if(!$moveToNode) {
			return false;
		}

		$this->change($id, $moveToNode[$prop_id]);
		
		if (is_int($number)) {
			$number--;
		}
		if ($number) {
			$this->moveUp($id, $number);
		}
	}

	protected function _insertLeafSpace($parentRight) {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		$sql = 'UPDATE ' . $this->getTable() . ' SET ' . $prop_left . ' = CASE WHEN ' . $prop_left . ' > ' . $parentRight .
			' THEN ' . $prop_left . ' + 2 ELSE ' . $prop_left . ' END, ' . $prop_right . ' = CASE WHEN ' . $prop_right . ' >= ' . $parentRight .
			' THEN ' . $prop_right . ' + 2 ELSE ' . $prop_right . ' END WHERE ' . $prop_right . ' >= ' . $parentRight;
		\DB::query($sql)->execute();
	}

	protected function _getMax() {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		$result = \DB::select(\DB::expr('max(' . $prop_right . ')'))->from($this->getTable())->execute()->current();
		return (empty($result[$prop_right])) ? 0 : $result[$prop_right];
	}

	protected function _getMin() {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		$result = \DB::select(\DB::expr('min(' . $prop_left . ')'))->from($this->getTable())->execute()->current();
		return (empty($result[$prop_left])) ? 0 : $result[$prop_left];
	}
}
