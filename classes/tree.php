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
}
