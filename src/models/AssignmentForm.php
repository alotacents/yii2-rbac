<?php

namespace alotacents\rbac\models;

use alotacents\rbac\traits\AuthManagerTrait;
use Yii;
use yii\base\Model;
use alotacents\rbac\Module;


/**
 * Description of Assignment
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 2.5
 */
class AssignmentForm extends Model
{
    use AuthManagerTrait;

    public $roles;

    public $permissions;

    private $_user;

    private $_inheritedPermission = [];

    public function rules()
    {
        return [
            [['roles', 'permissions'], 'default', 'value'=>[]],
            [['roles', 'permissions'], 'each', 'rule'=>['string']],
        ];
    }

    public static function findUser($id){

        if (($module = Module::getInstance()) !== null) {
            $module = Yii::$app;
        }

        $user = $module->getUser();

        $class = $user->identityClass;

        //$class = $this->userClassName;
        if (is_scalar($id) && ($user = $class::findIdentity($id)) !== null) {
            return new static(['user'=>$user]);
        }

        return null;
    }

    public function setUser($user){

        $authManager = static::getAuthManager();

        if($user !== null) {

            $assignments = $authManager->getAssignments($user->id);
            $roles = $authManager->getRolesByUser($user->id);
            $permissions = $authManager->getPermissionsByUser($user->id);

            $direct = array_intersect_key($permissions, $assignments);
            $inherited = array_diff_key($permissions, $assignments);

            $this->_inheritedPermission = array_keys($inherited);

        } else {
            $roles = [];
            $permissions = [];
        }

        $this->roles = array_keys($roles);
        $this->permissions = array_keys($permissions);

        $this->_user = $user;
    }

    public function getUser(){
        return $this->_user;
    }

    public function getUserId(){
        return $this->_user->id;
    }

    public function getInheritedPermissions(){
        if($this->_inheritedPermission === null){
            $this->_inheritedPermission = [];
        }
        return $this->_inheritedPermission;
    }
    /**
     * Find role
     * @param string $id
     * @return null|\self
     */
    public static function findOne($id)
    {
        $model = new static();

        $authManager = static::getAuthManager();
        
        $class = Module::getInstance()->user->identityClass;
        
        //$class = $this->userClassName;
        if (is_scalar($id) && ($user = $class::findIdentity($id)) !== null) {
            //$row = get_object_vars($object);
//            foreach ($user as $name => $value) {
//                if ($model->canSetProperty($name)) {
//                    $model->{$name} = $value;
//                }
//            }
            $model->userId = $user->getId();

            $model->_user = $user;
            
            return $model;
        }
        
        return null;
    }

    public function apply($runValidation = true, $attributeNames = null){

        if ($runValidation && !$this->validate($attributeNames)) {
            return false;
        }

        try {

            $authManager = static::getAuthManager();
            $methods = [
                'assign' => [],
                'revoke' => [],
            ];

            $userId = $this->getUserId();

            $assignedRoles = $authManager->getRolesByUser($userId);
            $assignedPermissions = $authManager->getPermissionsByUser($userId);

            $assignedRoleNames = array_keys($assignedRoles);
            $assignedPermissionNames = array_keys($assignedPermissions);

            $roles = $this->roles;
            if(isset($roles)) {

                $remove = array_diff($assignedRoleNames, $roles);
                foreach ($remove as $name) {
                    if(($item = $authManager->getRole($name)) !== null) {
                        if($authManager->revoke($item, $userId) !== null) {
                            $methods['assign'][] = $item;
                        }
                    }
                }

                $add = array_diff($roles, $assignedRoleNames);
                foreach ($add as $name) {
                    if(($item = $authManager->getRole($name)) !== null) {
                        if($authManager->assign($item, $userId) !== null) {
                            $methods['revoke'][] = $item;
                        }
                    }
                }
            }

            $permissions = $this->permissions;
            if(isset($permissions)) {

                $remove = array_diff($assignedPermissionNames, $permissions);
                foreach ($remove as $name) {
                    if(isset($assignedPermissions[$name]) && ($item = $assignedPermissions[$name]) !== null) {
                        if($authManager->revoke($item, $userId) !== null) {
                            $methods['assign'][] = $item;
                        }
                    }
                }

                $add = array_diff($permissions, $assignedPermissionNames);
                foreach ($add as $name) {
                    if(($item = $authManager->getPermission($name)) !== null) {
                        if($authManager->assign($item, $userId) !== null) {
                            $methods['revoke'][] = $item;
                        }
                    }
                }

            }

        } catch (\Throwable $e) {
            foreach ($methods as $method=>$items) {
                foreach ($items as $item) {
                    $authManager->{$method}($item, $userId);
                }
            }
//            throw $e;
            return false;
        } catch (\Exception $e) {
            foreach ($methods as $method=>$items) {
                foreach ($items as $item) {
                    $authManager->{$method}($item, $userId);
                }
            }
//            throw $e;
            return false;
        }

        return true;

    }

        /**
     * Grands a roles from a user.
     * @param array $items
     * @return integer number of successful grand
     */
    public function assign($items)
    {
        $authManager = static::getAuthManager();

        if(!is_array($items)){
            $items = (array) $items;
        }

        if (!$this->getIsNewRecord()) {
            $id = $this->userId;
            
            $success = 0;
            $assignments = [];
            foreach ($items as $name) {
                try {
                    if(($item = $authManager->getRole($name)) === null){
                        $item = $authManager->getPermission($name);
                    }
                    $authManager->assign($item, $id);
                    $assignments[] = $item;
                    $success++;
                } catch (\Exception $e) {
                    foreach ($assignments as $item) {
                        $authManager->revoke($item, $id);
                    }
                    throw $e;
                } catch (\Throwable $e) {
                    foreach ($assignments as $item) {
                        $authManager->revoke($item, $id);
                    }
                    throw $e;
                }
            }
        }

        return true;
    }

    /**
     * Revokes a roles from a user.
     * @param array $items
     * @return integer number of successful revoke
     */
    public function revoke($items)
    {
        $authManager = static::getAuthManager();

        if(!is_array($items)){
            $items = (array) $items;
        }

        $id = $this->userId;

        $success = 0;
        $assignments = [];
        foreach ($items as $name) {
            try {
                if(($item = $authManager->getRole($name)) === null){
                    $item = $authManager->getPermission($name);
                }
                $authManager->revoke($item, $id);
                $assignments[] = $item;
                $success++;
            } catch (\Exception $e) {
                foreach($assignments as $item){
                    $authManager->assign($item, $id);
                }
                throw $e;
            } catch (\Throwable $e){
                foreach($assignments as $item){
                    $authManager->assign($item, $id);
                }
                throw $e;
            }
        }

        return true;
    }


    /**
     * Get items
     * @return array
     */
    public function getItems()
    {
        $authManager = static::getAuthManager();

        $items = array_merge($authManager->getRoles(), $authManager->getPermissions());

        return $items;
    }

    /**
     * Get type name
     * @param  mixed $type
     * @return string|array
     */
    public function getAvailable()
    {
        $authManager = static::getAuthManager();

        $items = $this->getItems();

        unset($items[$this->_item->name]);

        $exclude = $authManager->getAssignments($this->userId);

        /*
                $exclude = $am->getAncestors($itemName);
                $exclude[$itemName] = $item;
                $exclude = array_merge($exclude, $item->getChildren());
                $authItems = $am->getAuthItems();
                $validChildTypes = $this->getValidChildTypes();
        
                foreach ($authItems as $childName => $childItem) {
                    if (in_array($childItem->type, $validChildTypes) && !isset($exclude[$childName])) {
                        $options[$this->capitalize(
                            $this->getItemTypeText($childItem->type, true)
                        )][$childName] = $childItem->description;
                    }
                }
        */
        return array_diff_key($items, $exclude);
    }

}
