<?php

namespace alotacents\rbac\models;

use alotacents\rbac\components\ArrayQuery;
use alotacents\rbac\traits\AuthManagerTrait;
use alotacents\rbac\validators\ClassNameValidator;
use alotacents\rbac\validators\PhpSyntaxValidator;
use Yii;
use yii\base\Model;
use alotacents\rbac\Module;
use yii\helpers\FileHelper;
use yii\web\View;
use yii\helpers\ArrayHelper;

use SplFileObject;
use ReflectionClass;

/**
 * Rule
 *
 * @author
 * @since 1.0
 */
class RuleForm extends Model
{

    use AuthManagerTrait;

    /**
     * @var string name of the rule
     */
    public $name;

    private $_ns;
    //public $ns = 'alotacents\rbac\rules';

    public $baseClass = \yii\rbac\Rule::class;

    /**
     * @var string Rule classname.
     */
    public $className;

    public $bizRule;

    /**
     * @var Rule
     */
    private $_rule;

    public static function findRule($name)
    {
        $authManager = static::getAuthManager();

        if(($rule = $authManager->getRoule($name)) !== null && !($rule instanceof \__PHP_Incomplete_Class)){
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

    /**
     * @inheritdoc
     */
    public function generate()
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
            $oldContent = null;
            if (is_file($path)) {
                $create = ($oldContent = @file_get_contents($path)) !== $content;
            } else {
                $dir = dirname($path);
                if (($create = FileHelper::createDirectory($dir)) === false) {
                    $this->addError('ns', "Failed to create directory \"{$dir}\".");
                }
            }

            if ($create === true) {
                if (@file_put_contents($path, $content) === false) {
                    $this->addError('ns', "Failed to write the file \"{$path}\".");
                    return false;
                } else {
                    @chmod($path, 0666);

                    $validator = new PhpSyntaxValidator();
                    if (!$validator->validate($path, $error)) {
                        if (isset($oldContent)) {
                            @file_put_contents($path, $oldContent);
                        }
                        $this->addError('bizRule', $error);
                    }
                }
            }

            return !$this->hasErrors();
        } else {
            return false;
        }
    }


}
