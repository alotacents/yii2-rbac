<?php

namespace alotacents\rbac\models;

use Yii;
use yii\base\Model;
use alotacents\rbac\Module;
use yii\base\ModelEvent;
use yii\db\AfterSaveEvent;
use yii\helpers\FileHelper;
use alotacents\rbac\traits\AuthManagerTrait;
use alotacents\rbac\validators\ClassNameValidator;
use alotacents\rbac\validators\PhpSyntaxValidator;

use SplFileObject;
use ReflectionClass;

/**
 * Rule
 *
 * @author
 * @since 1.0
 */
class Rule extends Model
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

    /**
     * @var string name of the rule
     */
    public $name;
    
    //public $ns = 'alotacents\rbac\rules';

    public $baseClass = \yii\rbac\Rule::class;

    /**
     * @var string Rule classname.
     */
    public $className;

    public $bizRule;

    private $_ns;
    /**
     * @var Rule
     */
    private $_rule;

    public static function findRule($name)
    {
        $authManager = static::getAuthManager();

        if(($rule = $authManager->getRule($name)) !== null && !($rule instanceof \__PHP_Incomplete_Class)){
            return new static(['rule'=>$rule]);
        }

        return null;
    }

    public function getNs()
    {
        if ($this->_ns === null) {
            $module = Module::getInstance();
            //$module = Yii::$app->controller->module;
            $this->_ns = $module->ruleNamespace;
            //$this->_ns = '@app/rbac/rules';
        }
        return $this->_ns;
    }

    public function setNs($value)
    {
        $this->_ns = $value;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['ns', 'className', 'baseClass'], 'filter', 'filter' => 'trim'],
            [
                ['ns'],
                'filter',
                'filter' => function ($value) {
                    return trim($value, '\\');
                }
            ],
            [['ns', 'className', 'baseClass', 'name'], 'required'],

            [['className'], 'match', 'pattern' => '/^\w+$/', 'message' => 'Only word characters are allowed.'],
            [
                ['ns', 'baseClass'],
                'match',
                'pattern' => '/^[\w\\\\]+$/',
                'message' => 'Only word characters and backslashes are allowed.'
            ],
            [['ns'], 'validateNamespace'],
            [['className'], ClassNameValidator::class, 'skipOnEmpty' => false],
            [['baseClass'], 'validateClass', 'params' => ['extends' => \yii\rbac\Rule::class]],
            [['name'],
                'validateUniqueRule',
                'when' => function ($model, $attibute) {
                    return $model->rule === null || ($model->rule->name !== $model->name);
                }
            ],
            [['bizRule'], 'safe']

        ];
    }

    /**
     * Validates the namespace.
     *
     * @param string $attribute Namespace variable.
     */
    public function validateNamespace($attribute, $params, $validator)
    {
        $value = $this->{$attribute};
        $value = ltrim($value, '\\');
        $path = Yii::getAlias('@' . str_replace('\\', '/', $value), false);
        if ($path === false) {
            $validator->addError($this, $attribute, 'Namespace must be associated with an existing directory.', []);
        }
    }

    public function validateUniqueRule($attribute, $params, $validator){

        $authManager = static::getAuthManager();

        if(($message = $validator->message) === null){
            $message = Yii::t('yii', '{attribute} "{value}" has already been taken.');
        }

        $value = $this->{$attribute};
        if ($authManager->getRule($value) !== null) {
            $validator->addError($this, $attribute, $message, []);
        }
    }


    /**
     * An inline validator that checks if the attribute value refers to an existing class name.
     * If the `extends` option is specified, it will also check if the class is a child class
     * of the class represented by the `extends` option.
     * @param string $attribute the attribute being validated
     * @param array $params the validation options
     */
    public function validateClass($attribute, $params, $validator)
    {
        $value = $this->{$attribute};
        try {
            if (class_exists($value)) {
                if (isset($params['extends'])) {
                    if (ltrim($value, '\\') !== ltrim($params['extends'], '\\') && !is_subclass_of($value,
                            $params['extends'])) {
                        $message = Yii::t('yii',
                            '{attribute} "{value}" must extend from {extends} or its child class.');
                        $validator->addError($this, $attribute, $message, [
                            'extends' => $params['extends'],
                        ]);
                    }
                }
            } else {
                $message = Yii::t('yii', 'Class "{value}" does not exist or has syntax error.');
                $validator->addError($this, $attribute, $message, []);
            }
        } catch (\Exception $e) {
            $message = Yii::t('yii', 'Class "{value}" does not exist or has syntax error.');
            $validator->addError($this, $attribute, $message, []);
        } catch (\Throwable $e){
            $message = Yii::t('yii', 'Class "{value}" does not exist or has syntax error.');
            $validator->addError($this, $attribute, $message, []);
        }
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'ns' => 'Namespace',
            'name' => 'Rule Name',
            'className' => 'Class Name',
            'baseClass' => 'Base Class',
            'bizRule' => 'Business Rule',
        ];
    }

    public function setRule($rule)
    {

        if ($rule !== null) {

            foreach ($rule as $name => $value) {
                if ($this->canSetProperty($name)) {
                    $this->$name = $value;
                }
            }

            $className = get_class($rule); //$rule::class;

            $ns = '';
            $baseClass = \yii\rbac\Rule::class;
            $bizRule = '';
            if (class_exists($className)) {
                $exportClass = new ReflectionClass($className);
                $ns = $exportClass->getNamespaceName();
                $className = $exportClass->getShortName();
                $baseClass = $exportClass->getExtensionName();
                if ($exportClass->hasMethod('execute')) {
                    $method = $exportClass->getMethod('execute');

                    $filename = $method->getFileName();
                    $start_line = $method->getStartLine(); // it's actually - 1, otherwise you wont get the function() block
                    $end_line = $method->getEndLine();

                    $source = new SplFileObject($filename, 'r');
                    $source->seek($start_line);
                    //while(++$start_line <= $end_line && !$file->eof()) {
                    while (($line_no = $source->key()) < $end_line && !$source->eof()) {
                        $line = $source->current();
                        if ($line_no === $start_line) {
                            $line = ltrim($line, "{ \t\n\r\0\x0B");
                        } elseif ($line_no === ($end_line - 1)) {
                            $line = rtrim($line, "} \t\n\r\0\x0B");
                        }
                        //$bizRule .= $start_line.':'.($file->key()+1);
                        $bizRule .= $line;
                        $source->next();
                    }
                    unset($source);
                }
            }

            $this->ns = $ns;
            $this->className = $className;
            $this->bizRule = trim($bizRule);

            $this->_rule = $rule;
        }

    }

    /**
     * Get item
     * @return Item
     */
    public function getRule()
    {
        return $this->_rule;
    }

    protected function getFileName()
    {
        $file = Yii::getAlias('@' . str_replace('\\', '/', $this->ns)) . '/' . $this->className . '.php';
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);
    }

    public static function primaryKey()
    {
        return ['name'];
    }

    /**
     * @inheritdoc
     */
    public function createRule()
    {

        if ($this->validate()) {
            $path = $this->getFileName();

            //$content = $this->render('rule.php', $params);

            $baseClass = '\\' . ltrim($this->baseClass, '\\');
            $content = <<<PHP
<?php
namespace {$this->ns};

use Yii;

/**
 * Rule represents a business constraint that may be associated with a role, permission or assignment.
 *
 */
class {$this->className} extends {$baseClass}
{
    /**
     * @inheritdoc
     */
    public \$name = '{$this->name}';

    /**
     * @inheritdoc
     */
    public function execute(\$user, \$item, \$params)
    {
        {$this->bizRule}
    }
}
PHP;
            if (is_file($path)) {
                $create = @file_get_contents($path) !== $content;
            } else {
                $dir = dirname($path);
                if (($create = FileHelper::createDirectory($dir)) === false) {
                    $this->addError('ns', "Failed to create directory \"{$dir}\".");
                }
            }

            if ($create === true) {

                $tmpPath = $path.'.tmp';
                if (file_put_contents($tmpPath, $content) === false) {
                    $this->addError('ns', "Failed to write the file \"{$tmpPath}\".");
                } else {
                    $validator = new PhpSyntaxValidator();
                    if ($validator->validate($tmpPath, $error)) {
                        if (@rename($tmpPath, $path) === true) {
                            @chmod($path, 0666);
                        } else {
                            $this->addError('ns', "Failed to write the file \"{$path}\".");
                        }
                    } else {
                        FileHelper::unlink($tmpPath);
                        $this->addError('bizRule', $error);
                    }


                }

            }

            if($this->hasErrors()){
                return null;
            }

            $class = $this->ns . '\\' . $this->className;

            return new $class();

        } else {
            return null;
        }
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

        $rule = $this->createRule();
        if($rule === null) {
            return false;
        }

        if($authManager->add($rule) === false){
            return false;
        }

        $changedAttributes = array_fill_keys(array_keys($values), null);
        $this->_rule = $rule;
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
        if ($this->_rule === null) {
            foreach ($this as $name => $value) {
                if (isset($names[$name])) {
                    $values[$name] = $value;
                }
            }
        } else {
            foreach ($this as $name => $value) {
                if (isset($names[$name]) && (!property_exists($this->_rule, $name) || $value !== $this->_rule->{$name})) {
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

        $rule = $this->createRule();
        if($rule !== null) {
            $row = (int) $authManager->update($oldName, $rule);
        } else {
            $row = 0;
        }

        $changedAttributes = [];
        foreach ($values as $name => $value) {
            $changedAttributes[$name] = property_exists($this->_rule, $name) ? $this->_rule->{$name} : null;
        }
        $this->_rule = $rule;

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

            if($this->_rule !== null && $authManager->remove($this->_rule) === true){
                $this->_rule = null;
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
        return $this->_rule === null;
    }

    /**
     * Sets the value indicating whether the record is new.
     * @param bool $value whether the record is new and should be inserted when calling [[save()]].
     * @see getIsNewRecord()
     */
    public function setIsNewRecord($value)
    {
        $this->_rule = $value ? null : $this->creatItem($this->getPrimaryKey(false));
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

        $this->_rule = $record->rule;

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
            return isset($this->_rule->{$keys[0]}) ? $this->_rule->{$keys[0]} : null;
        }

        $values = [];
        foreach ($keys as $name) {
            $values[$name] = isset($this->_rule->{$name}) ? $this->_rule->{$name} : null;
        }

        return $values;
    }

}
