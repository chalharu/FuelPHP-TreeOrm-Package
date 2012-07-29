<?php
/**
 * Tree Orm
 *
 * @package	fuel-treeorm
 * @version	0.1
 * @author	chalharu
 * @license	MIT License
 * @copyright	Copyright 2012, chalharu
 * @link	http://chrysolite.hatenablog.com/
 */

namespace TreeOrm;

/**
 * Tree class
 */
class Tree
{
	/**
	 * @var	\Orm\Model	モデルインスタンス
	 */
	protected $_obj;

	/**
	 * @var	string	テーブル名
	 */
	protected $_table;

	/**
	 * @var	array	ツリー構造に必要なキー及び名前
	 */
	protected $_property;

	/**
	 * @var	array	ツリー構造に必要なキー及び名前のデフォルト値
	 */
	public static $property = array( 'left' => 'lft', 'right' => 'rght', 'parent_id' => 'parent_id', 'id' => 'id');

	/**
	 * インスタンスの生成
	 * @param	mixed	$model	モデル名もしくは\Orm\Modelのインスタンス
	 * @return	Tree
	 */
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

	/**
	 * Constructor
	 * @param  \Orm\Model
	 */
	public function __construct(\Orm\Model $obj)
	{
		$this->_obj = $obj;
		$this->_table = call_user_func(get_class($obj).'::table');
		$props = call_user_func(get_class($obj) . '::observers', 'TreeOrm\\Observer_Tree');
		$this->_property = isset($props['property']) && is_array($props['property']) ? array_merge(static::$property, $props['property']) : static::$property ;
	}

	/**
	 * 親ノードの取得
	 * @param	int	$id	主キー
	 * @return	array|boolean	親ノードが存在すればその配列、存在しなければfalseを返す
	 */
	public function getParentNode($id = null) {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		if (is_array($id)) {
			extract (array_merge(array($prop_id => null), $id));
		}
		if (empty ($id)) {
			$id = $this->_obj->id;
		}
		
		$node = \DB::select($prop_parent_id)->from($this->_table)->where($prop_id,$id)->execute()->current();

		if ($node) {
			$parent = \DB::select()->from($this->_table)->where($prop_id,$node[$prop_parent_id])->execute()->current();
			return $parent;
		}
		return false;
	}

	/**
	 * 子ノードの取得
	 * @param	int	$id	主キーもしくは、パラメータの連想配列
	 * @param	boolean	$direct	直下の子のみを対象とするか
	 * @param	string	$orderColumn	ソートカラム名
	 * @param	string	$orderDirection	ソート方向(ASC|DESC)
	 * @param	int	$limit	取得最大数
	 * @return	array|boolean	元のノードが存在していれば子ノードの配列、存在していなければfalseを返す
	 */
	public function children($id = null, $direct = false, $orderColumn = null, $orderDirection = null, $limit = null) {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		if (is_array($id)) {
			extract (array_merge(array($prop_id => null), $id));
		}
		if ($id === null) {
			$id = $this->_obj->id;
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
					return \DB::select()->from($this->_table)->where($prop_parent_id,null)->or_where($prop_parent_id,0)
						->order_by($orderColumn, $orderDirection)->limit($limit)->execute();
				else
					return \DB::select()->from($this->_table)->where($prop_parent_id,null)->or_where($prop_parent_id,0)
						->order_by($orderColumn, $orderDirection)->execute();
			}
			if($limit)
				return \DB::select()->from($this->_table)->where($prop_parent_id,$id)->order_by($orderColumn, $orderDirection)->limit($limit)->execute();
			else
				return \DB::select()->from($this->_table)->where($prop_parent_id,$id)->order_by($orderColumn, $orderDirection)->execute();
		}
		if($id === null) {
			if($limit)
				return \DB::select()->from($this->_table)->order_by($orderColumn, $orderDirection)->limit($limit)->execute();
			else
				return \DB::select()->from($this->_table)->order_by($orderColumn, $orderDirection)->execute();
		}
		$node = \DB::select($prop_id,$prop_left,$prop_right)->from($this->_table)->where($prop_id,$id)->execute()->current();
		if(!$node) {
			return false;
		}
		if($limit)
			return \DB::select()->from($this->_table)->where($prop_left, '>',$node[$prop_left])->where($prop_right, '<',$node[$prop_right])
				->order_by($orderColumn, $orderDirection)->limit($limit)->execute();
		else
			return \DB::select()->from($this->_table)->where($prop_left, '>',$node[$prop_left])->where($prop_right, '<',$node[$prop_right])
				->order_by($orderColumn, $orderDirection)->execute();
	}
	
	/**
	 * 子ノード数の取得
	 * @param	int	$id	主キーもしくは、パラメータの連想配列
	 * @param	boolean	$direct	直下の子のみを対象とするか
	 * @return	int|boolean	元のノードが存在していれば子ノード数、存在していなければfalseを返す
	 */
	public function childCount($id = null, $direct = false) {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		if (is_array($id)) {
			extract (array_merge(array($prop_id => null), $id));
		}
		if ($id === null) {
			$id = $this->_obj->id;
		} elseif(!$id) {
			$id = null;
		}
		if ($direct) {
			if($id === null) {
				$result = \DB::select(\DB::expr('COUNT(' . $prop_id . ')'))->from($this->_table)
					->where($prop_parent_id,null)->or_where($prop_parent_id,0)->execute()->current();
				return (int)$result['COUNT(' . $prop_id . ')'];
			}
			$result = \DB::select(\DB::expr('COUNT(' . $prop_id . ')'))->from($this->_table)->where($prop_parent_id,$id)->execute()->current();
			return (int)$result['COUNT(' . $prop_id . ')'];
		}
		if($id === null) {
			$result = \DB::select(\DB::expr('COUNT(' . $prop_id . ')'))->from($this->_table)->execute()->current();
			return (int)$result['COUNT(' . $prop_id . ')'];
		}
		$node = \DB::select($prop_id,$prop_left,$prop_right)->from($this->_table)->where($prop_id,$id)->execute()->current();
		if(!$node) {
			return false;
		}
		$result = \DB::select(\DB::expr('COUNT(' . $prop_id . ')'))->from($this->_table)->where($prop_left, '>',$node[$prop_left])->where($prop_right, '<',$node[$prop_right])
			->execute()->current();
		return (int)$result['COUNT(' . $prop_id . ')'];
	}

	/**
	 * パスの取得
	 * @param	int	$id	対象ノードのキー
	 * @return	array|boolean	元のノードが存在していればルートから順番に配列として、存在していなければfalseを返す
	 */
	public function getPath($id = null) {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		if (is_array($id)) {
			extract (array_merge(array($prop_id => null), $id));
		}
		if (empty ($id)) {
			$id = $this->_obj->id;
		}

		$node = \DB::select($prop_id,$prop_left,$prop_right)->from($this->_table)->where($prop_id,$id)->execute()->current();
		if(!$node) {
			return false;
		}
		return \DB::select()->from($this->_table)->where($prop_left, '<=',$node[$prop_left])->where($prop_right, '>=',$node[$prop_right])
			->order_by($prop_left, 'asc')->execute();
	}

	/**
	 * ノードの再配置
	 * @param	int	$id	対象ノードのキーもしくは、パラメータの連想配列
	 * @param	string	$field	ソートカラム名
	 * @param	string	$order	ソート方向(ASC|DESC)
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
			$id = $this->_obj->id;
		}
		
		$nodes = $this->children($id, true, $field, $order);
		if ($nodes) {
			foreach ($nodes as $node) {
				$this->moveDown($node[$prop_id], true);
				$this->reorder($node[$prop_id], $field, $order);
			}
		}
	}

	/**
	 * ノードの入れ替え
	 * @param	int	$id1	対象ノード1のキー
	 * @param	int	$id2	対象ノード2のキー
	 * @return	boolean	成功したかどうか
	 */
	public function change($id1 = null, $id2 = null) {
		if (!$id1) {
			return false;
		}
		if (!$id2) {
			return false;
		}
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		
		$node1 = \DB::select($prop_id,$prop_left,$prop_right)->from($this->_table)->where($prop_id,$id1)->execute()->current();
		if(!$node1) {
			return false;
		}
		$node2 = \DB::select($prop_id,$prop_left,$prop_right)->from($this->_table)->where($prop_id,$id2)->execute()->current();
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
		
		$sql = 'UPDATE ' . $this->_table .
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
		return true;
	}

	/**
	 * ノードの同じ階層の中で位置を下げる
	 * @param	int	$id	対象ノードのキー
	 * @param	int|boolean	$number	移動回数、TRUEを指定した場合、端まで移動
	 * @return	boolean	成功したかどうか
	 */
	public function moveDown($id = null, $number = 1) {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		if (is_array($id)) {
			extract (array_merge(array($prop_id => null), $id));
		}
		if (!$number) {
			return false;
		}
		if (empty ($id)) {
			$id = $this->_obj->id;
		}
		
		$node = \DB::select($prop_id,$prop_left,$prop_right,$prop_parent_id)->from($this->_table)->where($prop_id,$id)->execute()->current();
		if(!$node) {
			return false;
		}
		if(!$node[$prop_right] && ($node[$prop_parent_id] == null || $node[$prop_parent_id] == 0)) {
			$updateSet = array();
			$updateSet[$prop_left] = ($this->_getMin() ?: 2) - 1;
			$updateSet[$prop_right] = ($this->_getMax() ?: 1) + 1;
			\DB::update($this->_table)->set($updateSet)->where($prop_id, $node[$prop_id])->execute();
			return false;
		}
		
		$parentNode = \DB::select($prop_id,$prop_left,$prop_right,$prop_parent_id)->from($this->_table)->where($prop_id,$node[$prop_parent_id])->execute()->current();
		if(!$parentNode) {
			return false;
		}
		if(!$parentNode[$prop_right]) {
			$this->moveDown($parentNode[$prop_id], true);
			$parentNode = \DB::select($prop_id,$prop_left,$prop_right,$prop_parent_id)->from($this->_table)->where($prop_id,$node[$prop_parent_id])->execute()->current();
		}
		if(!$node[$prop_right]){
			$this->_insertLeafSpace($parentNode[$prop_right]);
			$updateSet = array();
			$updateSet[$prop_left] = $parentNode[$prop_right];
			$updateSet[$prop_right] = $parentNode[$prop_right] + 1;
			\DB::update($this->_table)->set($updateSet)->where($prop_id, $node[$prop_id])->execute();
			return false;
		}
		
		$moveToNode = \DB::select()->from($this->_table)
			->where($prop_left,\DB::expr('(SELECT MIN(' . $prop_left . ') FROM ' . $this->_table . ' WHERE ' . $prop_left . ' > ' . $node[$prop_right] . ' AND ' . $prop_right . ' < ' . $parentNode[$prop_right] . ')'))
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
		return true;
	}

	/**
	 * ノードの同じ階層の中で位置を上げる
	 * @param	int	$id	対象ノードのキー
	 * @param	int|boolean	$number	移動回数、TRUEを指定した場合、端まで移動
	 * @return	boolean	成功したかどうか
	 */
	public function moveUp($id = null, $number = 1) {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		if (is_array($id)) {
			extract (array_merge(array($prop_id => null), $id));
		}
		if (!$number) {
			return false;
		}
		if (empty ($id)) {
			$id = $this->_obj->id;
		}
		
		$node = \DB::select($prop_id,$prop_left,$prop_right,$prop_parent_id)->from($this->_table)->where($prop_id,$id)->execute()->current();
		if(!$node) {
			return false;
		}
		if(!$node[$prop_left] && ($node[$prop_parent_id] == null || $node[$prop_parent_id] == 0)) {
			$updateSet = array();
			$updateSet[$prop_left] = ($this->_getMin() ?: 2) - 1;
			$updateSet[$prop_right] = ($this->_getMax() ?: 1) + 1;
			\DB::update($this->_table)->set($updateSet)->where($prop_id, $node[$prop_id])->execute();
			return false;
		}
		
		$parentNode = \DB::select($prop_id,$prop_left,$prop_right,$prop_parent_id)->from($this->_table)->where($prop_id,$node[$prop_parent_id])->execute()->current();
		if(!$parentNode) {
			return false;
		}
		if(!$parentNode[$prop_left]) {
			$this->moveUp($parentNode[$prop_id], true);
			$parentNode = \DB::select($prop_id,$prop_left,$prop_right,$prop_parent_id)->from($this->_table)->where($prop_id,$node[$prop_parent_id])->execute()->current();
		}
		if(!$node[$prop_right]){
			$this->_insertLeafSpace($parentNode[$prop_left] + 1);
			$updateSet = array();
			$updateSet[$prop_left] = $parentNode[$prop_left] + 1;
			$updateSet[$prop_right] = $parentNode[$prop_left] + 2;
			\DB::update($this->_table)->set($updateSet)->where($prop_id, $node[$prop_id])->execute();
			return false;
		}
		
		$moveToNode = \DB::select()->from($this->_table)
			->where($prop_right,\DB::expr('(SELECT MAX(' . $prop_right . ') FROM ' . $this->_table . ' WHERE ' . $prop_right . ' < ' . $node[$prop_left] . ' AND ' . $prop_left . ' > ' . $parentNode[$prop_left] . ')'))
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
		return true;
	}

	/**
	 * 指定の場所にノードを追加するスペースを作る
	 * @param	int	$parentRight	対象位置
	 */
	protected function _insertLeafSpace($parentRight) {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		$sql = 'UPDATE ' . $this->_table . ' SET ' . $prop_left . ' = CASE WHEN ' . $prop_left . ' > ' . $parentRight .
			' THEN ' . $prop_left . ' + 2 ELSE ' . $prop_left . ' END, ' . $prop_right . ' = CASE WHEN ' . $prop_right . ' >= ' . $parentRight .
			' THEN ' . $prop_right . ' + 2 ELSE ' . $prop_right . ' END WHERE ' . $prop_right . ' >= ' . $parentRight;
		\DB::query($sql)->execute();
	}

	/**
	 * ノードの右端を取得
	 * @return	int	rghtの最大値
	 */
	protected function _getMax() {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		$result = \DB::select(\DB::expr('max(' . $prop_right . ')'))->from($this->_table)->execute()->current();
		return (empty($result[$prop_right])) ? 0 : $result[$prop_right];
	}

	/**
	 * ノードの左端を取得
	 * @return	int	lftの最小値
	 */
	protected function _getMin() {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		$result = \DB::select(\DB::expr('min(' . $prop_left . ')'))->from($this->_table)->execute()->current();
		return (empty($result[$prop_left])) ? 0 : $result[$prop_left];
	}

	/**
	 * ノードの添字振りなおし
	 */
	public function reset() {
		extract($this->_property, EXTR_PREFIX_ALL, 'prop');
		$sql = 'UPDATE ' . $this->_table .
			' SET ' . $prop_left . ' = (SELECT COUNT(*) FROM (SELECT ' . $prop_left . ' as seq FROM ' . $this->_table .
			' UNION ALL SELECT ' . $prop_right . ' as seq FROM ' . $this->_table . ') as LftRgt WHERE seq <= ' . $prop_left . '), ' .
			$prop_right . ' = (SELECT COUNT(*) FROM (SELECT ' . $prop_left . ' as seq FROM ' . $this->_table .
			' UNION ALL SELECT ' . $prop_right . ' as seq FROM ' . $this->_table . ') as LftRgt WHERE seq <= ' . $prop_right . ')';
		\DB::query($sql)->execute();
	}
}
