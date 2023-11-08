<?php

namespace alotacents\rbac\models;

use Yii;
use \alotacents\rbac\base\Item;

/**
 * Class Role
 * @package alotacents\rbac\models
 */
class Role extends Item
{

    /**
     * @param $name
     * @return null|static
     */
    public static function findItem($name)
    {
        $authManager = static::getAuthManager();

        if (($item = $authManager->getRole($name)) !== null) {
            return new static(['item' => $item]);
        }

        return null;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return \yii\rbac\Item::TYPE_ROLE;
    }

}
