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
class Assignment extends Model
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

        $class = Yii::$app->controller->module->user->identityClass;
        
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


    /**
     * Get all available and assigned roles/permission
     * @return array
     *
    public function getItems()
    {
        $manager = Configs::authManager();
        $available = [];
        foreach (array_keys($manager->getRoles()) as $name) {
            $available[$name] = 'role';
        }

        foreach (array_keys($manager->getPermissions()) as $name) {
            if ($name[0] != '/') {
                $available[$name] = 'permission';
            }
        }

        $assigned = [];
        foreach ($manager->getAssignments($this->id) as $item) {
            $assigned[$item->roleName] = $available[$item->roleName];
            unset($available[$item->roleName]);
        }

        return [
            'available' => $available,
            'assigned' => $assigned,
        ];
    }
    */
}
