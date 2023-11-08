<?php

namespace alotacents\rbac\models;

use Yii;
use \alotacents\rbac\base\ItemForm;

/**
 * This is the model class for table "tbl_auth_item".
 *
 * @property string $name
 * @property integer $type
 * @property string $description
 * @property string $ruleName
 * @property string $data
 *
 * @property Item $item
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class PermissionForm extends ItemForm
{

    public static function findItem($name)
    {
        $authManager = static::getAuthManager();

        if(($item = $authManager->getPermission($name)) !== null){
            return new static(['item'=>$item]);
        }

        return null;
    }

    public function getType(){
        return \yii\rbac\Item::TYPE_PERMISSION;
    }

}
