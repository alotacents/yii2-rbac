<?php

namespace alotacents\rbac;

use Yii;
use yii\di\Instance;
use yii\base\BootstrapInterface;
use yii\base\Module as BaseModule;
use yii\base\InvalidConfigException;
use yii\helpers\Inflector;
use yii\web\User;

/**
 * Videcom module definition class
 */
class Module extends BaseModule implements BootstrapInterface
{
    /**
     * @inheritdoc
     */
    public $controllerNamespace = __NAMESPACE__ . '\\controllers';

    //public $ruleNamespace = __NAMESPACE__ . '\rules';
    public $ruleNamespace = 'app\rbac\rules';

    // User Component Needed
    public $user = 'user';

    public $userColumns = [
        'id',
    ];

    public $layout = 'tabs';

    /**
     * @inheritdoc
     */
    public function bootstrap($app)
    {
        if ($app instanceof \yii\web\Application) {
            /* Set Default Route and URL Rules.
             * $app->getUrlManager()->addRules([
                ['class' => 'yii\web\UrlRule', 'pattern' => $this->id, 'route' => $this->id . '/default/index'],
                ['class' => 'yii\web\UrlRule', 'pattern' => $this->id . '/<id:\w+>', 'route' => $this->id . '/default/view'],
                ['class' => 'yii\web\UrlRule', 'pattern' => $this->id . '/<controller:[\w\-]+>/<action:[\w\-]+>', 'route' => $this->id . '/<controller>/<action>'],
            ], false);
            */
        } elseif ($app instanceof yii\console\Application) {

            if (($spos = strrpos(__NAMESPACE__, '\\')) !== false) {
                $extensionName = substr(__NAMESPACE__, 0, $spos);
            } else {
                $extensionName = __NAMESPACE__;
            }

            if ($app->enableCoreCommands) {

                $coreCommands = $app->coreCommands();
                if(!isset($app->controllerMap['migrate'])){
                    $app->controllerMap['migrate'] = $coreCommands['migrate'];
                }

                if(is_string($app->controllerMap['migrate'])){
                    $app->controllerMap['migrate'] = [
                        'class' => $app->controllerMap['migrate'],
                    ];
                }

                $migrate = $app->controllerMap['migrate'];
                if(is_array($migrate) && isset($migrate['class'])){
                    if(!isset($migrate['migrationNamespaces'])){
                        //$migrate['migrationNamespaces'] = ['app\\migrations'];
                        $migrate['migrationNamespaces'] = [];
                    }
                    $namespace = $extensionName . '\\migrations';
                    if(!in_array($namespace, $migrate['migrationNamespaces'])){
                        $migrate['migrationNamespaces'][] = $namespace;
                    }

                    $app->controllerMap['migrate'] = $migrate;
                }
            }

            Yii::setAlias('@'.str_replace('\\', DIRECTORY_SEPARATOR, $extensionName) . DIRECTORY_SEPARATOR . 'migrations', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'console/migrations');

        }
    }


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->user = Instance::ensure($this->user, User::class, $this);

        // custom initialization code goes here

        if (Yii::$app instanceof \yii\web\Application) {

            // add module to breadcrumb links
            $this->on(Module::EVENT_BEFORE_ACTION, function($event){
                /*$parts = explode('/', $this->getUniqueId());
                foreach ($parts as $i=>$part){
                    $label = Inflector::camel2words($part);
                    $route = '/' . implode('/', array_slice($parts, 0, $i+1));

                    $this->view->params['breadcrumbs'][] = ['label' => $label, 'url' => [$route]];
                }*/

                $part = strtok($this->getUniqueId(), "/");
                $route = '';
                while ($part !== false){
                    $label = Inflector::camel2words($part);
                    $route .= '/' . $part;

                    $this->view->params['breadcrumbs'][] = ['label' => $label, 'url' => [$route]];

                    $part = strtok("/");
                }
            });
        } elseif (Yii::$app instanceof \yii\console\Application) {

        }

    }
    
    public function getUser(){
        /*if(!($this->user instanceof User)){
            $type = User::class;
            $valueType = is_object($this->user) ? get_class($this->user) : gettype($this->user);
            throw new InvalidConfigException("Invalid data type: $valueType. $type is expected.");
        }*/
        return $this->user;
    }
}
