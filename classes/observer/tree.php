<?php
namespace TreeOrm;

class Observer_Tree extends \Orm\Observer
{
	public static $property = array( 'left' => 'lft', 'right' => 'rght', 'parent_id' => 'parent_id', 'id' => 'id');
	protected $_property;

	public function __construct($class)
	{
		$props = $class::observers(get_class($this));
		$this->_property = isset($props['property']) && is_array($props['property']) ? array_merge($props['property'],static::$property) : static::$property ;
	}
	
	public function before_save(\Orm\Model $obj)
	{
		if ($obj->is_new()){
			if($obj->{$this->_property['parent_id']}){
				// parent_idがセットされている
				$parentNode = $obj->query()->where($this->_property['id'], '=', $obj->{$this->_property['parent_id']})->get();
				$parentNode = array_shift($parentNode);
				if(!$parentNode)
					throw new FuelException();
				$table = call_user_func(get_class($obj).'::table');
				$sql = 'UPDATE ' . $table . 
					' SET ' . $this->_property['left'] . ' = CASE WHEN ' . $this->_property['left'] . ' > ' . $parentNode{$this->_property['right']} .
					' THEN ' . $this->_property['left'] . ' + 2' .
					' ELSE ' . $this->_property['left'] . ' END,' .
					$this->_property['right'] . ' = CASE WHEN ' . $this->_property['right'] . ' >= ' . $parentNode{$this->_property['right']} .
					' THEN ' . $this->_property['right'] . ' + 2' .
					' ELSE ' . $this->_property['right'] . ' END' .
					' WHERE ' . $this->_property['right']  . ' >= ' . $parentNode{$this->_property['right']};
				$query = \DB::query($sql)->execute();
				$obj->{$this->_property['left']} = $parentNode{$this->_property['right']};
				$obj->{$this->_property['right']} = $parentNode{$this->_property['right']} + 1;
//				Debug::error($sql);
			} else {
				// parent_idがセットされていない
				$nodeCount = $obj->query()->count();
				if($nodeCount != 0)
					throw new FuelException();
				$obj->{$this->_property['left']} = 1;
				$obj->{$this->_property['right']} = 2;
				$obj->{$this->_property['parent_id']} = 0;
			}
		}

		if (!$obj->is_new() && $obj->is_changed()){
			if($obj->{$this->_property['parent_id']}){ 				
				$table = call_user_func(get_class($obj).'::table');
				$oldNode =\DB::select()->from($table)->where($this->_property['id'], '=', $obj->{$this->_property['id']})->execute()->current();

				if($oldNode{$this->_property['parent_id']} != $obj->{$this->_property['parent_id']}){
					$parentNode = $obj->query()->where($this->_property['id'], '=', $obj->{$this->_property['parent_id']})->get();
					$parentNode = array_shift($parentNode);
					if(!$parentNode)
						throw new FuelException();
					if( $parentNode{$this->_property['right']} < $oldNode{$this->_property['left']}
						&& $oldNode{$this->_property['left']} < $oldNode{$this->_property['right']}){
						$sql = 'UPDATE ' . $table . 
							' SET ' . $this->_property['left'] . ' = CASE WHEN ' . $this->_property['left'] .
							' BETWEEN ' . $oldNode{$this->_property['left']} . ' AND ' . $oldNode{$this->_property['right']} .
							' THEN ' . ($parentNode{$this->_property['right']} - $oldNode{$this->_property['left']}) . ' + ' . $this->_property['left'] .
							' WHEN ' . $this->_property['left'] .
							' BETWEEN ' . $parentNode{$this->_property['right']} . ' AND ' . ($oldNode{$this->_property['left']} - 1) .
							' THEN ' . ($oldNode{$this->_property['right']} - $oldNode{$this->_property['left']} + 1) . ' + ' . $this->_property['left'] .
							' ELSE ' . $this->_property['left'] . ' END, ' .
							$this->_property['right'] . ' = CASE WHEN ' . $this->_property['right'] .
							' BETWEEN ' . $oldNode{$this->_property['left']} . ' AND ' . $oldNode{$this->_property['right']} .
							' THEN ' . ($parentNode{$this->_property['right']} - $oldNode{$this->_property['left']}) . ' + ' . $this->_property['right'] .
							' WHEN ' . $this->_property['right'] .
							' BETWEEN ' . $parentNode{$this->_property['right']} . ' AND ' . ($oldNode{$this->_property['left']} - 1) .
							' THEN ' . ($oldNode{$this->_property['right']} - $oldNode{$this->_property['left']} + 1) . ' + ' . $this->_property['right'] .
							' ELSE ' . $this->_property['right'] . ' END ' .
							' WHERE ' . $this->_property['left'] .
							' BETWEEN ' . $parentNode{$this->_property['left']} . ' AND ' . $oldNode{$this->_property['right']};
					} else if( $oldNode{$this->_property['right']} < $parentNode{$this->_property['right']}
						&& $oldNode{$this->_property['left']} < $oldNode{$this->_property['right']}){
						$sql = 'UPDATE ' . $table .
							' SET ' . $this->_property['left'] . ' = CASE WHEN ' . $this->_property['left'] .
							' BETWEEN ' . $oldNode{$this->_property['left']} . ' AND ' . $oldNode{$this->_property['right']} .
							' THEN ' . ($parentNode{$this->_property['right']} - $oldNode{$this->_property['right']} - 1) . ' + ' . $this->_property['left'] .
							' WHEN ' . $this->_property['left'] .
							' BETWEEN ' . ($oldNode{$this->_property['right']} + 1) . ' AND ' . ($parentNode{$this->_property['right']} - 1) .
							' THEN ' . ($oldNode{$this->_property['left']} - $oldNode{$this->_property['right']} - 1) . ' + ' . $this->_property['left'] .
							' ELSE ' . $this->_property['left'] . ' END, ' .
							$this->_property['right'] . ' = CASE WHEN ' . $this->_property['right'] .
							' BETWEEN ' . $oldNode{$this->_property['left']} . ' AND ' . $oldNode{$this->_property['right']} .
							' THEN ' . ($parentNode{$this->_property['right']} - $oldNode{$this->_property['right']}  - 1) . ' + ' . $this->_property['right'] .
							' WHEN ' . $this->_property['right'] .
							' BETWEEN ' . ($oldNode{$this->_property['right']} + 1) . ' AND ' . ($parentNode{$this->_property['right']} - 1) .
							' THEN ' . ($oldNode{$this->_property['left']} - $oldNode{$this->_property['right']} - 1) . ' + ' . $this->_property['right'] .
							' ELSE ' . $this->_property['right'] . ' END ' .
							' WHERE ' . $this->_property['left'] .
							' BETWEEN ' . ($oldNode{$this->_property['left']} < $parentNode{$this->_property['left']} ? $oldNode{$this->_property['left']} : $parentNode{$this->_property['left']}) . ' AND ' . $parentNode{$this->_property['right']};
					} else {
						throw new \FuelException();
					}
					$query = \DB::query($sql)->execute();
					$oldNode =\DB::select()->from($table)->where($this->_property['id'], '=', $obj->{$this->_property['id']})->execute()->current();
				}
				$obj->{$this->_property['left']} = $oldNode{$this->_property['left']};
				$obj->{$this->_property['right']} = $oldNode{$this->_property['right']};
			}
		}
	}

	public function before_delete(\Orm\Model $obj)
	{		
		$table = call_user_func(get_class($obj).'::table');
		$sql = 'DELETE FROM ' . $table .
			' WHERE ' . $this->_property['left'] . ' BETWEEN ' . $obj->{$this->_property['left']} . ' AND ' . $obj->{$this->_property['right']} .
			' AND NOT ' . $this->_property['left'] . ' IN(' . $obj->{$this->_property['left']} . ',' . $obj->{$this->_property['right']} . ')';
		$query = \DB::query($sql)->execute();
	}
}

