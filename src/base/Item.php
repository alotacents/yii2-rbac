<?php

namespace alotacents\rbac\base;

use alotacents\rbac\traits\AuthManagerTrait;
use Yii;
use yii\base\InvalidArgumentException;
use yii\base\Model;
use yii\base\ModelEvent;
use yii\db\AfterSaveEvent;
use yii\helpers\Inflector;


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
abstract class Item extends Model
{
    use AuthManagerTrait;

    /**
     * @event Event an event that is triggered when the record is initialized via [[init()]].
     */
    const EVENT_INIT = 'init';
    /**
     * @event Event an event that is triggered after the record is created and populated with query result.
     */
    const EVENT_AFTER_FIND = 'afterFind';
    /**
     * @event ModelEvent an event that is triggered before inserting a record.
     * You may set [[ModelEvent::isValid]] to be `false` to stop the insertion.
     */
    const EVENT_BEFORE_INSERT = 'beforeInsert';
    /**
     * @event AfterSaveEvent an event that is triggered after a record is inserted.
     */
    const EVENT_AFTER_INSERT = 'afterInsert';
    /**
     * @event ModelEvent an event that is triggered before updating a record.
     * You may set [[ModelEvent::isValid]] to be `false` to stop the update.
     */
    const EVENT_BEFORE_UPDATE = 'beforeUpdate';
    /**
     * @event AfterSaveEvent an event that is triggered after a record is updated.
     */
    const EVENT_AFTER_UPDATE = 'afterUpdate';
    /**
     * @event ModelEvent an event that is triggered before deleting a record.
     * You may set [[ModelEvent::isValid]] to be `false` to stop the deletion.
     */
    const EVENT_BEFORE_DELETE = 'beforeDelete';
    /**
     * @event Event an event that is triggered after a record is deleted.
     */
    const EVENT_AFTER_DELETE = 'afterDelete';
    /**
     * @event Event an event that is triggered after a record is refreshed.
     * @since 2.0.8
     */
    const EVENT_AFTER_REFRESH = 'afterRefresh';

    const SCENARIO_CREATE = 'create';
    const SCENARIO_UPDATE = 'update';

    public $name;
    public $description;
    public $ruleName;
    public $data;

    /**
     * @var array|null old attribute values indexed by attribute names.
     * This is `null` if the record [[isNewRecord|is new]].
     */
    private $_item;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name','type'], 'required'],
            [['type'], 'integer'],
            [['name', 'description', 'ruleName'], 'trim'],
            ['name', 'filter', 'filter' => function ($value) {
                // change name to a variable name
                return Inflector::variablize($value);
            }],
            ['name', 'string', 'max' => 64],
            ['name', 'match', 'pattern' => '/[\w\s\/\-\.]+/'],

            [['name'], 'validateUniqueItem', 'when' => function($model, $attibute) {
                return  $model->item === null || ($model->item->name !== $model->name);
            }],
            [['description', 'ruleName', 'data'], 'string'],
            [['ruleName'],'validateExistRule'],

            [['description','ruleName', 'data'], 'default', 'value' => null],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'name' => Yii::t('yii', 'Name'),
            'type' => Yii::t('yii', 'Type'),
            'description' => Yii::t('yii', 'Description'),
            'ruleName' => Yii::t('yii', 'Rule Name'),
            'data' => Yii::t('yii', 'Data'),
        ];
    }

    public function validateUniqueItem($attribute, $params, $validator){

        $authManager = static::getAuthManager();

        if(($message = $validator->message) === null){
            $message = Yii::t('yii', '{attribute} "{value}" has already been taken.');
        }

        $value = $this->{$attribute};
        if ($authManager->getPermission($value) !== null || $authManager->getRole($value) !== null) {
            $validator->addError($this, $attribute, $message, []);
        }
    }

    public function validateExistRule($attribute, $params, $validator){

        $authManager = static::getAuthManager();

        $value = $this->{$attribute};
        if (is_string($value)) {
            if ($authManager->getRule($value) === null) {
                $message = Yii::t('yii', '{attribute} "{value}" does not exists');
                $validator->addError($this, $attribute, $message, []);
            }
        } else {
            $message = Yii::t('yii', '{attribute} must be a string.');
            $validator->addError($this, $attribute, $message, []);
        }
    }

    protected function createItem(){

        $authManager = static::getAuthManager();

        switch ($this->type){
            case \yii\rbac\Item::TYPE_ROLE:
                $item = $authManager->createRole($this->name);
                break;
            case \yii\rbac\Item::TYPE_PERMISSION:
                $item = $authManager->createPermission($this->name);
                break;
            default:
                $item = null;
        }

        if(isset($item)) {
            //$item->name = $model->name;
            $item->description = $this->description;
            $item->ruleName = $this->ruleName;
            $item->data = $this->data === null || $this->data === '' ? null : Json::decode($this->data);
        }

        return $item;
    }

    public function setItem($item){

        if($item !== null) {
            foreach ($item as $name => $value) {
                if ($this->canSetProperty($name)) {
                    $this->$name = $value;
                }
            }
        }

        $this->_item = $item;
    }

    public function getItem(){
        return $this->_item;
    }

    abstract public static function findItem($name);

    abstract public function getType();

    public static function getTypeList(){
        return [
            yii\rbac\Item::TYPE_PERMISSION => 'Permission',
            yii\rbac\Item::TYPE_ROLE => 'Role',
        ];
    }
    /**
     * Get type name
     * @param  mixed $type
     * @return string
     */
    public static function getTypeText($type)
    {
        if(!is_scalar($type)){
            throw new InvalidArgumentException('First parameter ($type) must be scalar. Input was: '.gettype($type));
        }

        $list = static::getTypeList();

        $text = isset($list[$type]) ? $list[$type] : "unknown type ({$type})";

        return $text;

    }

    public static function primaryKey()
    {
        return ['name'];
    }

    public function addChild($itemName){

        if ($this->getIsNewRecord()) {
            return false;
        }

        $authManager = static::getAuthManager();

        $child = $authManager->getPermission($itemName);
        if ($this->type == \yii\rbac\Item::TYPE_ROLE && $child === null) {
            $child = $authManager->getRole($itemName);
        }

        if($child === null){
            return false;
        }

        return $authManager->addChild($this->_item, $child);
    }

    public function removeChild($itemName){

        if ($this->getIsNewRecord()) {
            return false;
        }

        $authManager = static::getAuthManager();

        $child = $authManager->getPermission($itemName);
        if ($this->type == \yii\rbac\Item::TYPE_ROLE && $child === null) {
            $child = $authManager->getRole($itemName);
        }

        if($child === null){
            return false;
        }

        return $authManager->removeChild($this->_item, $child);
    }

    public function addParent($itemName){

        if ($this->getIsNewRecord()) {
            return false;
        }

        $authManager = static::getAuthManager();

        $parent = $authManager->getPermission($itemName);
        if ($parent === null) {
            $parent = $authManager->getRole($itemName);
        }

        if($parent === null){
            return false;
        }

        return $authManager->addChild($parent, $this->_item);
    }

    public function removeParent($itemName){

        if ($this->getIsNewRecord()) {
            return false;
        }

        $authManager = static::getAuthManager();

        $parent = $authManager->getPermission($itemName);
        if ($parent === null) {
            $parent = $authManager->getRole($itemName);
        }

        if($parent === null){
            return false;
        }

        return $authManager->removeChild($parent, $this->_item);
    }
    
    public function save($runValidation = true, $attributeNames = null)
    {
        if ($this->getIsNewRecord()) {
            return $this->insert($runValidation, $attributeNames);
        }

        return $this->update($runValidation, $attributeNames) !== false;
    }


    public function insert($runValidation = true, $attributeNames = null)
    {
        if ($runValidation && !$this->validate($attributeNames)) {
            Yii::info('Model not inserted due to validation error.', __METHOD__);
            return false;
        }

        return $this->insertInternal($attributeNames);
    }

    public function update($runValidation = true, $attributeNames = null)
    {
        if ($runValidation && !$this->validate($attributeNames)) {
            Yii::info('Model not updated due to validation error.', __METHOD__);
            return false;
        }

        return $this->updateInternal($attributeNames);
    }

    /**
     * Inserts an ActiveRecord into DB without considering transaction.
     * @param array $attributes list of attributes that need to be saved. Defaults to `null`,
     * meaning all attributes that are loaded from DB will be saved.
     * @return bool whether the record is inserted successfully.
     */
    protected function insertInternal($attributes = null)
    {
        if (!$this->beforeSave(true)) {
            return false;
        }

        $authManager = static::getAuthManager();

        $values = $this->getAttributes();

        $item = $this->createItem();
        if($item === null) {
            return false;
        }

        if($authManager->add($item) === false){
            return false;
        }

        $changedAttributes = array_fill_keys(array_keys($values), null);
        $this->_item = $item;
        $this->afterSave(true, $changedAttributes);

        return true;
    }

    /**
     * @see update()
     * @param array $attributes attributes to update
     * @return int|false the number of rows affected, or false if [[beforeSave()]] stops the updating process.
     * @throws StaleObjectException
     */
    protected function updateInternal($attributes = null)
    {
        if (!$this->beforeSave(false)) {
            return false;
        }

        $names = array_flip($this->attributes());
        $values = [];
        if ($this->_item === null) {
            foreach ($this as $name => $value) {
                if (isset($names[$name])) {
                    $values[$name] = $value;
                }
            }
        } else {
            foreach ($this as $name => $value) {
                if (isset($names[$name]) && (!property_exists($this->_rule, $name) || $value !== $this->_item->{$name})) {
                    $values[$name] = $value;
                }
            }
        }

        if (empty($values)) {
            $this->afterSave(false, $values);
            return 0;
        }

        $authManager = static::getAuthManager();

        $oldName = $this->getOldPrimaryKey(false);

/*
        $item = clone $this->_item;

        $item->name = $this->name;
        $item->description = $this->description;
        $item->ruleName = $this->ruleName;
        $item->data = $this->data === null || $this->data === '' ? null : Json::decode($this->data);
*/

        $item = $this->createItem();
        if($item !== null) {
            $row = (int) $authManager->update($oldName, $item);
        } else {
            $row = 0;
        }

        $changedAttributes = [];
        foreach ($values as $name => $value) {
            $changedAttributes[$name] = property_exists($this->_item, $name) ? $this->_item->{$name} : null;
        }
        $this->_item = $item;

        $this->afterSave(false, $changedAttributes);

        return $row;
    }


    /**
     * Deletes the table row corresponding to this active record.
     *
     * This method performs the following steps in order:
     *
     * 1. call [[beforeDelete()]]. If the method returns `false`, it will skip the
     *    rest of the steps;
     * 2. delete the record from the database;
     * 3. call [[afterDelete()]].
     *
     * In the above step 1 and 3, events named [[EVENT_BEFORE_DELETE]] and [[EVENT_AFTER_DELETE]]
     * will be raised by the corresponding methods.
     *
     * @return int|false the number of rows deleted, or `false` if the deletion is unsuccessful for some reason.
     * Note that it is possible the number of rows deleted is 0, even though the deletion execution is successful.
     * @throws StaleObjectException if [[optimisticLock|optimistic locking]] is enabled and the data
     * being deleted is outdated.
     * @throws Exception in case delete failed.
     */
    public function delete()
    {
        $result = 0;
        if ($this->beforeDelete()) {

            $authManager = static::getAuthManager();

            if($this->_item !== null && $authManager->remove($this->_item) === true){
                $this->_item = null;
                $result = 1;
            }

            $this->afterDelete();
        }

        return $result;
    }

    /**
     * Returns a value indicating whether the current record is new.
     * @return bool whether the record is new and should be inserted when calling [[save()]].
     */
    public function getIsNewRecord()
    {
        return $this->_item === null;
    }

    /**
     * Sets the value indicating whether the record is new.
     * @param bool $value whether the record is new and should be inserted when calling [[save()]].
     * @see getIsNewRecord()
     */
    public function setIsNewRecord($value)
    {
        $this->_item = $value ? null : $this->creatItem($this->getPrimaryKey(false));
    }

    /**
     * Initializes the object.
     * This method is called at the end of the constructor.
     * The default implementation will trigger an [[EVENT_INIT]] event.
     */
    public function init()
    {
        parent::init();
        $this->trigger(self::EVENT_INIT);
    }

    /**
     * This method is called when the AR object is created and populated with the query result.
     * The default implementation will trigger an [[EVENT_AFTER_FIND]] event.
     * When overriding this method, make sure you call the parent implementation to ensure the
     * event is triggered.
     */
    public function afterFind()
    {
        $this->trigger(self::EVENT_AFTER_FIND);
    }

    /**
     * This method is called at the beginning of inserting or updating a record.
     *
     * The default implementation will trigger an [[EVENT_BEFORE_INSERT]] event when `$insert` is `true`,
     * or an [[EVENT_BEFORE_UPDATE]] event if `$insert` is `false`.
     * When overriding this method, make sure you call the parent implementation like the following:
     *
     * ```php
     * public function beforeSave($insert)
     * {
     *     if (!parent::beforeSave($insert)) {
     *         return false;
     *     }
     *
     *     // ...custom code here...
     *     return true;
     * }
     * ```
     *
     * @param bool $insert whether this method called while inserting a record.
     * If `false`, it means the method is called while updating a record.
     * @return bool whether the insertion or updating should continue.
     * If `false`, the insertion or updating will be cancelled.
     */
    public function beforeSave($insert)
    {
        $event = new ModelEvent();
        $this->trigger($insert ? self::EVENT_BEFORE_INSERT : self::EVENT_BEFORE_UPDATE, $event);

        return $event->isValid;
    }

    /**
     * This method is called at the end of inserting or updating a record.
     * The default implementation will trigger an [[EVENT_AFTER_INSERT]] event when `$insert` is `true`,
     * or an [[EVENT_AFTER_UPDATE]] event if `$insert` is `false`. The event class used is [[AfterSaveEvent]].
     * When overriding this method, make sure you call the parent implementation so that
     * the event is triggered.
     * @param bool $insert whether this method called while inserting a record.
     * If `false`, it means the method is called while updating a record.
     * @param array $changedAttributes The old values of attributes that had changed and were saved.
     * You can use this parameter to take action based on the changes made for example send an email
     * when the password had changed or implement audit trail that tracks all the changes.
     * `$changedAttributes` gives you the old attribute values while the active record (`$this`) has
     * already the new, updated values.
     *
     * Note that no automatic type conversion performed by default. You may use
     * [[\yii\behaviors\AttributeTypecastBehavior]] to facilitate attribute typecasting.
     * See http://www.yiiframework.com/doc-2.0/guide-db-active-record.html#attributes-typecasting.
     */
    public function afterSave($insert, $changedAttributes)
    {
        $this->trigger($insert ? self::EVENT_AFTER_INSERT : self::EVENT_AFTER_UPDATE, new AfterSaveEvent([
            'changedAttributes' => $changedAttributes,
        ]));
    }

    /**
     * This method is invoked before deleting a record.
     *
     * The default implementation raises the [[EVENT_BEFORE_DELETE]] event.
     * When overriding this method, make sure you call the parent implementation like the following:
     *
     * ```php
     * public function beforeDelete()
     * {
     *     if (!parent::beforeDelete()) {
     *         return false;
     *     }
     *
     *     // ...custom code here...
     *     return true;
     * }
     * ```
     *
     * @return bool whether the record should be deleted. Defaults to `true`.
     */
    public function beforeDelete()
    {
        $event = new ModelEvent();
        $this->trigger(self::EVENT_BEFORE_DELETE, $event);

        return $event->isValid;
    }

    /**
     * This method is invoked after deleting a record.
     * The default implementation raises the [[EVENT_AFTER_DELETE]] event.
     * You may override this method to do postprocessing after the record is deleted.
     * Make sure you call the parent implementation so that the event is raised properly.
     */
    public function afterDelete()
    {
        $this->trigger(self::EVENT_AFTER_DELETE);
    }

    /**
     * Repopulates this active record with the latest data.
     *
     * If the refresh is successful, an [[EVENT_AFTER_REFRESH]] event will be triggered.
     * This event is available since version 2.0.8.
     *
     * @return bool whether the row still exists in the database. If `true`, the latest data
     * will be populated to this active record. Otherwise, this record will remain unchanged.
     */
    public function refresh()
    {
        /* @var $record BaseActiveRecord */
        $record = static::findItem($this->getPrimaryKey(false));
        return $this->refreshInternal($record);
    }

    /**
     * Repopulates this active record with the latest data from a newly fetched instance.
     * @param BaseActiveRecord $record the record to take attributes from.
     * @return bool whether refresh was successful.
     * @see refresh()
     * @since 2.0.13
     */
    protected function refreshInternal($record)
    {
        if ($record === null) {
            return false;
        }
        foreach ($this->attributes() as $name) {
            $this->{$name} = isset($record->{$name}) ? $record->{$name} : null;
        }

        $this->_item = $record->item;

//        $this->_oldAttributes = $record->_oldAttributes;
//        $this->_related = [];
//        $this->_relationsDependencies = [];
        $this->afterRefresh();

        return true;
    }

    /**
     * This method is called when the AR object is refreshed.
     * The default implementation will trigger an [[EVENT_AFTER_REFRESH]] event.
     * When overriding this method, make sure you call the parent implementation to ensure the
     * event is triggered.
     * @since 2.0.8
     */
    public function afterRefresh()
    {
        $this->trigger(self::EVENT_AFTER_REFRESH);
    }

    /**
     * Returns a value indicating whether the given active record is the same as the current one.
     * The comparison is made by comparing the table names and the primary key values of the two active records.
     * If one of the records [[isNewRecord|is new]] they are also considered not equal.
     * @param ActiveRecordInterface $record record to compare to
     * @return bool whether the two active records refer to the same row in the same database table.
     */
    public function equals($record)
    {
        if ($this->getIsNewRecord() || $record->getIsNewRecord()) {
            return false;
        }

        return get_class($this) === get_class($record) && $this->getPrimaryKey() === $record->getPrimaryKey();
    }

    /**
     * Returns the primary key value(s).
     * @param bool $asArray whether to return the primary key value as an array. If `true`,
     * the return value will be an array with column names as keys and column values as values.
     * Note that for composite primary keys, an array will always be returned regardless of this parameter value.
     * @property mixed The primary key value. An array (column name => column value) is returned if
     * the primary key is composite. A string is returned otherwise (null will be returned if
     * the key value is null).
     * @return mixed the primary key value. An array (column name => column value) is returned if the primary key
     * is composite or `$asArray` is `true`. A string is returned otherwise (null will be returned if
     * the key value is null).
     */
    public function getPrimaryKey($asArray = false)
    {
        $keys = $this->primaryKey();
        if (!$asArray && count($keys) === 1) {
            return isset($this->{$keys[0]}) ? $this->{$keys[0]} : null;
        }

        $values = [];
        foreach ($keys as $name) {
            $values[$name] = isset($this->{$name}) ? $this->{$name} : null;
        }

        return $values;
    }

    public function getOldPrimaryKey($asArray = false)
    {
        $keys = $this->primaryKey();
        if (empty($keys)) {
            throw new Exception(get_class($this) . ' does not have a primary key. You should either define a primary key for the corresponding table or override the primaryKey() method.');
        }
        if (!$asArray && count($keys) === 1) {
            return isset($this->_item->{$keys[0]}) ? $this->_item->{$keys[0]} : null;
        }

        $values = [];
        foreach ($keys as $name) {
            $values[$name] = isset($this->_item->{$name}) ? $this->_item->{$name} : null;
        }

        return $values;
    }



}
