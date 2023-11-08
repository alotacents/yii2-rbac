<?php

namespace alotacents\rbac\components;

use yii\base\Behavior;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\rbac\DbManager;
use yii\rbac\Item;
use Yii;
use yii\rbac\PhpManager;

/**
 * Auth module behavior for the authorization manager.
 *
 * @property Yii\rbac\DbManager $owner The authorization manager.
 */
class AuthBehavior extends Behavior
{
    /**
     * @var array cached relations between the auth items.
     */
    public $_items;

    public function loadItems($refresh = false){

        $authManager = $this->owner;

        if($refresh || !isset($this->_items)){

            $tree = [];

            if($authManager instanceof DbManager){

                $items = [];
                $query = (new Query())->from($authManager->itemTable);
                foreach ($query->all($authManager->db) as $row) {
                    $items[$row['name']] = $row;
                }

                $query = (new Query())->from($authManager->itemChildTable);
                foreach ($query->all($authManager->db) as $row) {

                    $parentName = $row['parent'];

                    $item = isset($items[$parentName]) ? $items[$parentName] : [];
                    if (!isset($tree[$parentName])) {
                        $tree[$parentName] = [
                            'item' => [
                                'name' => $parentName,
                                'type' => isset($item['type']) ? $item['type'] : null,
                                'description' => isset($item['description']) ? $item['description'] : null,
                                'ruleName' => isset($item['rule_name']) ? $item['rule_name'] : null,
                                'data' => isset($item['data']) ? $item['data'] : null,
                                'createdAt' => isset($item['created_at']) ? $item['created_at'] : null,
                                'updatedAt' => isset($item['updated_at']) ? $item['updated_at'] : null,
                            ],
                            'parents' => [],
                            'children' => [],
                        ];
                    }

                    $childName = $row['child'];
                    $tree[$row['parent']]['children'][] = $childName;

                    $item = isset($items[$childName]) ? $items[$childName] : [];
                    if (!isset($tree[$childName])) {
                        $tree[$childName] = [
                            'item' => [
                                'name' => $childName,
                                'type' => isset($item['type']) ? $item['type'] : null,
                                'description' => isset($item['description']) ? $item['description'] : null,
                                'ruleName' => isset($item['rule_name']) ? $item['rule_name'] : null,
                                'data' => isset($item['data']) ? $item['data'] : null,
                                'createdAt' => isset($item['created_at']) ? $item['created_at'] : null,
                                'updatedAt' => isset($item['updated_at']) ? $item['updated_at'] : null,
                            ],
                            'parents' => [],
                            'children' => [],
                        ];
                    }

                    $tree[$childName]['parents'][] = $parentName;

                }

            } elseif($authManager instanceof PhpManager){

                if (is_file($authManager->itemFile)) {
                    $items = require $file;
                    $itemsMtime = @filemtime($authManager->itemFile);
                } else {
                    $items = [];
                    $itemsMtime = null;
                }

                foreach ($items as $parentName => $item) {

                    if (!isset($tree[$parentName])) {
                        $tree[$parentName] = [
                            'item' => [
                                'name' => $parentName,
                                'type' => isset($item['type']) ? $item['type'] : null,
                                'description' => isset($item['description']) ? $item['description'] : null,
                                'ruleName' => isset($item['ruleName']) ? $item['ruleName'] : null,
                                'data' => isset($item['data']) ? $item['data'] : null,
                                'createdAt' => $itemsMtime,
                                'updatedAt' => $itemsMtime,
                            ],
                            'parents' => [],
                            'children' => []
                        ];
                    }

                    if (isset($item['children'])) {
                        foreach ($item['children'] as $childName) {

                            $tree[$parentName]['children'][] = $childName;

                            $itemChild = isset($items[$childName]) ? $items[$childName] : [];
                            if (!isset($tree[$childName])) {
                                $tree[$childName] = [
                                    'item' => [
                                        'name' => $childName,
                                        'type' => isset($item['type']) ? $item['type'] : null,
                                        'description' => isset($itemChild['description']) ? $itemChild['description'] : null,
                                        'ruleName' => isset($itemChild['ruleName']) ? $itemChild['ruleName'] : null,
                                        'data' => isset($itemChild['data']) ? $itemChild['data'] : null,
                                        'createdAt' => $itemsMtime,
                                        'updatedAt' => $itemsMtime,
                                    ],
                                    'parents' => [],
                                    'children' => []
                                ];
                            }

                            $tree[$childName]['parents'][] = $parentName;
                        }
                    }
                }

            }

            $this->_items = $tree;

        }

    }

    /**
     * Get type name
     * @param  mixed $type
     * @return string|array
     */
    public function getChildren($refresh = false)
    {
        if($refresh || !isset($this->children)){
            if($this->getIsNewRecord()) {
                $this->children = [];
            } else {
                $authManager = static::getAuthManager();

                $this->children = $authManager->getChildren($this->_item->name);
            }
        }
        return $this->children;
    }

    /**
     * Returns whether the given item has a specific child.
     * @param string $itemName name of the item.
     * @param string $childName name of the child.
     * @return boolean the result.
     */
    public function hasChild($itemName, $childName)
    {
        $children = $this->getChildren($itemName);
        if (in_array($childName, $children)) {
            return true;
        }
        return false;
    }

    public function getParents($itemName, $refresh = false)
    {
        $this->loadItems($refresh);

        $tree = $this->_items;

        $parents = [];
        $node = isset($tree[$itemName]) ? $tree[$itemName] : null;
        if ($node !== null) {
            if (isset($node['parents'])) {
                foreach ($node['parents'] as $parentName) {
                    if(isset($tree[$parentName], $tree[$parentName]['item'])){
                        $parents[$parentName] = $this->populateItem($tree[$parentName]['item']);
                    }
                }
            }
        }

        return $parents;

    }

    public function getAncestor($itemName, $refresh = false)
    {
        $this->loadItems($refresh);

        $ancestors = [];
        $this->getAncestorRecursive($itemName, $this->_items, $ancestors);

        array_multisort(ArrayHelper::getColumn($ancestors, 'level'), SORT_ASC, SORT_NUMERIC, ArrayHelper::getColumn($ancestors, 'name'), SORT_NATURAL|SORT_FLAG_CASE, SORT_ASC, $ancestors);

        return $ancestors;
    }

    protected function getAncestorRecursive($itemName, $tree, &$result, $depth = 0)
    {
        $node = isset($tree[$itemName]) ? $tree[$itemName] : null;

        if ($node !== null) {
            if ($depth > 0) {
                $item = $node['item'];
                $item['level'] = $depth;

                $result[] = $item;
            }
            if (isset($node['parents'])) {
                foreach ($node['parents'] as $parentName) {
                    /*if(isset($result[$parentName])){
                        $result[$parentName] = min($result[$parentName], $depth);
                    } else {
                        $result[$parentName] = $depth;
                    }*/
                    $this->getAncestorRecursive($parentName, $tree, $result, $depth + 1);
                }
            }
        }
    }

    public function getDescendant($itemName, $refresh = false)
    {
        $this->loadItems($refresh);

        $descendants = [];
        $this->getDescendantRecursive($itemName, $this->_items, $descendants);

        //array_multisort(ArrayHelper::getColumn($descendants, 'level'), SORT_ASC, SORT_NUMERIC, ArrayHelper::getColumn($descendants, 'name'), SORT_NATURAL|SORT_FLAG_CASE, SORT_ASC, $descendants);

        return $descendants;
    }

    protected function getDescendantRecursive($itemName, $tree, &$result, $depth = 0)
    {
        $node = isset($tree[$itemName]) ? $tree[$itemName] : null;

        if ($node !== null) {
            if ($depth > 0) {
                $item = $node['item'];
                $item['level'] = $depth;

                $result[] = $item;
            }
            if (isset($node['children'])) {
                foreach ($node['children'] as $childName) {
                    /*if(isset($result[$childName])){
                        $result[$childName] = min($result[$childName], $depth);
                    } else {
                        $result[$childName] = $depth;
                    }*/
                    $this->getDescendantRecursive($childName, $tree, $result, $depth + 1);
                }
            }
        }
    }
    
    /**
     * Returns whether the given item has a specific parent.
     * @param string $itemName name of the item.
     * @param string $parentName name of the parent.
     * @return boolean the result.
     */
    public function hasParent($itemName, $parentName)
    {
        $parents = $this->getParents($itemName);
        if (in_array($parentName, $parents)) {
            return true;
        }
        return false;
    }

    protected function populateItem($row)
    {
        static $closure = null;

        if($closure === null) {
            $closure = function ($row) {
                return $this->populateItem($row);
            };
        }

        return $closure->call(static::getAuthManager(), $row);
    }
}
