<?php
namespace TreeOrm;

class Tree
{
	public static $property = array( 'left' => 'lft', 'right' => 'rght', 'parent_id' => 'parent_id', 'id' => 'id');

	protected $obj;
	protected $table;
	
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
		$this->table = get_class($obj).'::table';
	}
	
	protected function getTable()
	{
		$this->_table = call_user_func(get_class($this->obj).'::table');
		return $this->_table;
	}
	
	public function getParentNode($id = null) {
		if (is_array($id)) {
			extract (array_merge(array('id' => null), $id));
		}
		if (empty ($id)) {
			$id = $this->obj->id;
		}
		$node = \DB::select('parent_id')->from($this->getTable())->where('id',$id)->execute()->current();

		if ($node) {
			$parent = \DB::select()->from($this->getTable())->where('id',$node['parent_id'])->execute()->current();
			return $parent;
		}
		return false;
	}

	public function children($id = null, $direct = false, $orderColumn = null, $orderDirection = null, $limit = null) {
		if (is_array($id)) {
			extract (array_merge(array('id' => null), $id));
		}
		if ($id === null) {
			$id = $this->obj->id;
		} elseif(!$id) {
			$id = null;
		}
		if (!$orderColumn) {
			$orderColumn = 'lft';
		}
		if (!$orderDirection) {
			$orderDirection = 'ASC';
		}
		if ($direct) {
			if($id === null) {
				if($limit)
					return \DB::select()->from($this->getTable())->where('parent_id',null)->or_where('parent_id',0)
						->order_by($orderColumn, $orderDirection)->limit($limit)->execute();
				else
					return \DB::select()->from($this->getTable())->where('parent_id',null)->or_where('parent_id',0)
						->order_by($orderColumn, $orderDirection)->execute();
			}
			if($limit)
				return \DB::select()->from($this->getTable())->where('parent_id',$id)->order_by($orderColumn, $orderDirection)->limit($limit)->execute();
			else
				return \DB::select()->from($this->getTable())->where('parent_id',$id)->order_by($orderColumn, $orderDirection)->execute();
		}
		if($id === null) {
			if($limit)
				return \DB::select()->from($this->getTable())->order_by($orderColumn, $orderDirection)->limit($limit)->execute();
			else
				return \DB::select()->from($this->getTable())->order_by($orderColumn, $orderDirection)->execute();
		}
		$node = \DB::select('id','lft','rght')->from($this->getTable())->where('id',$id)->execute()->current();
		if(!$node) {
			return false;
		}
		if($limit)
			return \DB::select()->from($this->getTable())->where('lft', '>',$node['lft'])->where('rght', '<',$node['rght'])
				->order_by($orderColumn, $orderDirection)->limit($limit)->execute();
		else
			return \DB::select()->from($this->getTable())->where('lft', '>',$node['lft'])->where('rght', '<',$node['rght'])
				->order_by($orderColumn, $orderDirection)->execute();
	}
	
	public function childCount($id = null, $direct = false) {
		if (is_array($id)) {
			extract (array_merge(array('id' => null), $id));
		}
		if ($id === null) {
			$id = $this->obj->id;
		} elseif(!$id) {
			$id = null;
		}
		if ($direct) {
			if($id === null) {
				$result = \DB::select(\DB::expr('COUNT(id)'))->from($this->getTable())
					->where('parent_id',null)->or_where('parent_id',0)->execute()->current();
				return (int)$result['COUNT(id)'];
			}
			$result = \DB::select(\DB::expr('COUNT(id)'))->from($this->getTable())->where('parent_id',$id)->execute()->current();
			return (int)$result['COUNT(id)'];
		}
		if($id === null) {
			$result = \DB::select(\DB::expr('COUNT(id)'))->from($this->getTable())->execute()->current();
			return (int)$result['COUNT(id)'];
		}
		$node = \DB::select('id','lft','rght')->from($this->getTable())->where('id',$id)->execute()->current();
		if(!$node) {
			return false;
		}
		$result = \DB::select(\DB::expr('COUNT(id)'))->from($this->getTable())->where('lft', '>',$node['lft'])->where('rght', '<',$node['rght'])
			->execute()->current();
		return (int)$result['COUNT(id)'];
	}

	public function getPath($id = null) {
		if (is_array($id)) {
			extract (array_merge(array('id' => null), $id));
		}
		if (empty ($id)) {
			$id = $this->obj->id;
		}

		$node = \DB::select('id','lft','rght')->from($this->getTable())->where('id',$id)->execute()->current();
		if(!$node) {
			return false;
		}
		return \DB::select()->from($this->getTable())->where('lft', '<=',$node['lft'])->where('rght', '>=',$node['rght'])
			->order_by('lft', 'asc')->execute();
	}
	
	/*
	 * - 'id' id of record to use as top node for reordering
	 * - 'field' Which field to use in reordering defaults to id
	 * - 'order' Direction to order either DESC or ASC (defaults to ASC)
	 */
	public function reorder($options = array()) {
		$options = array_merge(array('id' => null, 'field' => 'id', 'order' => 'ASC'), $options);
	}

	public function change($id1 = null, $id2 = null) {
		if (!$id1) {
			return false;
		}
		if (!$id2) {
			return false;
		}
		
		$node1 = \DB::select('id','lft','rght')->from($this->getTable())->where('id',$id1)->execute()->current();
		if(!$node1) {
			return false;
		}
		$node2 = \DB::select('id','lft','rght')->from($this->getTable())->where('id',$id2)->execute()->current();
		if(!$node2) {
			return false;
		}
		
		if($node1['rght'] < $node2['lft']) {
			$lft1	= $node1['lft'];
			$rght1	= $node1['rght'];
			$lft2	= $node2['lft'];
			$rght2	= $node2['rght'];
		} else	if($node2['rght'] < $node1['lft']) {
			$lft1	= $node2['lft'];
			$rght1	= $node2['rght'];
			$lft2	= $node1['lft'];
			$rght2	= $node1['rght'];
		} else {
			return false;
		}
		
		$sql = 'UPDATE ' . $this->getTable() .
			' SET lft = CASE WHEN lft BETWEEN ' . $lft1 . ' AND ' . $rght1 . ' THEN ' . ($rght2 - $rght1) . ' + lft ' .
			' WHEN lft BETWEEN ' . $lft2 . ' AND ' . $rght2 . ' THEN ' . ($lft1 - $lft2) . ' + lft ' .
			(($rght1 + 1) > ($lft2 - 1) ? '' : 
				(' WHEN lft BETWEEN ' . ($rght1  + 1) . ' AND ' . ($lft2 - 1) . ' THEN ' . ($lft1 + $rght2 - $rght1 - $lft2) . ' + lft ' )).
			' ELSE lft END, rght = CASE WHEN rght BETWEEN ' . $lft1 . ' AND ' . $rght1 . ' THEN ' . ($rght2 - $rght1) . ' + rght ' .
			' WHEN rght BETWEEN ' . $lft2 . ' AND ' . $rght2 . ' THEN ' . ($lft1 - $lft2) . ' + rght ' .
			(($rght1 + 1) > ($lft2 - 1) ? '' :
				(' WHEN rght BETWEEN ' . ($rght1 + 1) . ' AND ' . ($lft2 - 1) . ' THEN ' . ($lft1 + $rght2 - $rght1 - $lft2) . ' + rght ' )) .
			' ELSE rght END WHERE lft BETWEEN ' . $lft1 . ' AND ' . $rght2;
 		\DB::query($sql)->execute();
		
	}

	public function moveDown($id = null, $number = 1) {
		if (is_array($id)) {
			extract (array_merge(array('id' => null), $id));
		}
		if (!$number) {
			return false;
		}
		if (empty ($id)) {
			$id = $this->obj->id;
		}
		
		$node = \DB::select('id','lft','rght','parent_id')->from($this->getTable())->where('id',$id)->execute()->current();
		if(!$node) {
			return false;
		}
		
		$parentNode = \DB::select('id','lft','rght','parent_id')->from($this->getTable())->where('id',$node['parent_id'])->execute()->current();
		if(!$parentNode) {
			return false;
		}
		
		$moveToNode = \DB::select()->from($this->getTable())->where('lft',\DB::expr('(SELECT MIN(lft) FROM ' . $this->getTable() . ' WHERE lft > ' . $node['rght'] . ' AND rght < ' . $parentNode['rght'] . ')'))->execute()->current();
		if(!$moveToNode) {
			return false;
		}

		$this->change($id, $moveToNode['id']);
		
		if (is_int($number)) {
			$number--;
		}
		if ($number) {
			$this->moveUp($Model, $id, $number);
		}
	}

	public function moveUp($id = null, $number = 1) {
		if (is_array($id)) {
			extract (array_merge(array('id' => null), $id));
		}
		if (!$number) {
			return false;
		}
		if (empty ($id)) {
			$id = $this->obj->id;
		}
		
		$node = \DB::select('id','lft','rght','parent_id')->from($this->getTable())->where('id',$id)->execute()->current();
		if(!$node) {
			return false;
		}
		
		$parentNode = \DB::select('id','lft','rght','parent_id')->from($this->getTable())->where('id',$node['parent_id'])->execute()->current();
		if(!$parentNode) {
			return false;
		}
		
		$moveToNode = \DB::select()->from($this->getTable())
			->where('rght',\DB::expr('(SELECT MAX(rght) FROM ' . $this->getTable() . ' WHERE rght < ' . $node['lft'] . ' AND lft > ' . $parentNode['lft'] . ')'))
			->execute()->current();
		if(!$moveToNode) {
			return false;
		}

		$this->change($id, $moveToNode['id']);
		
		if (is_int($number)) {
			$number--;
		}
		if ($number) {
			$this->moveUp($Model, $id, $number);
		}
	}
}
