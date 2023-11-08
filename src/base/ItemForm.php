<?php

namespace alotacents\rbac\base;

use alotacents\rbac\traits\AuthManagerTrait;
use Yii;
use yii\base\InvalidArgumentException;
use yii\base\Model;
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
abstract class ItemForm extends Model
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
            [['ruleName'],'validateRuleName'],
            [['name', 'type'], 'required'],
            ['name', 'filter', 'filter' => function ($value) {
                // change name to a variable name
                return Inflector::variablize($value);
            }],
            ['name', 'string', 'max' => 64],
            ['name', 'match', 'pattern' => '/^[\w\s\/\-]+/'],
            [['description', 'data'], 'string'],
            [['type'], 'integer'],
            [['description', 'data', 'ruleName'], 'default', 'value' => null],
            [['name'], 'validateUniqueItem', 'when' => function($model, $attibute) {
                return  $model->item === null || ($model->item->name !== $model->name);
            }],
        ];
    }

    abstract public static function findItem($name);

    abstract public function getType();

    public function validateRuleName($attribute, $params, $validator){

        $authManager = static::getAuthManager();

        $value = $this->{$attribute};
        if (is_string($value)) {
            if (!$authManager->getRule($value)) {
                $message = Yii::t('yii', '{attribute} "{value}" does not exists');
                $params = [
                    'attribute' => $this->getAttributeLabel($attribute),
                    'value' => $value
                ];
                $validator->addError($this, $attribute, $message, $params);
            }
        } else {
            $message = Yii::t('yii', '{attribute} must be a string.');
            $params = [
                'attribute' => $this->getAttributeLabel($attribute),
            ];
            $validator->addError($this, $attribute, $message, $params);
        }
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

}
