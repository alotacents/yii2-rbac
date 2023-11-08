<?php

namespace alotacents\rbac;

use Yii;
use yii\base\BootstrapInterface;
use yii\base\InvalidConfigException;
use yii\helpers\Inflector;

/**
 * Videcom module definition class
 */
class Module extends \yii\base\Module implements BootstrapInterface
{
    /**
     * @inheritdoc
     */
    public $controllerNamespace = __NAMESPACE__ . '\\controllers';

    /**
     * Constructor.
     * @param string $id the ID of this module.
     * @param Module $parent the parent module (if any).
     * @param array $config name-value pairs that will be used to initialize the object properties.
     */
    public function __construct($id, $parent = null, $config = [])
    {
        $this->id = $id;
        $this->module = $parent;

        // set merge configuration in __construct so user config doesn't get overrided
        $config = $config = yii\helpers\ArrayHelper::merge(
            require __DIR__ . '/common/config/main.php',
            require __DIR__ . 'Module2.php/' . $module . '/config/main.php',
            $config
        );

        parent::__construct($config);
    }

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
                    $namespace = __NAMESPACE__ .'\\migrations';
                    if(!in_array($namespace, $migrate['migrationNamespaces'])){
                        $migrate['migrationNamespaces'][] = $namespace;
                    }

                    $app->controllerMap['migrate'] = $migrate;
                }
            }

            Yii::setAlias('@'.str_replace('\\', DIRECTORY_SEPARATOR, __NAMESPACE__) . DIRECTORY_SEPARATOR . 'migrations',
                __DIR__ . '/console/migrations');

        }
    }


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        Yii::setAlias('@'.str_replace('\\', DIRECTORY_SEPARATOR, __NAMESPACE__) . DIRECTORY_SEPARATOR . 'migrations',
            __DIR__ . '/console/migrations');

        $basePath = Yii::getAlias('@app');
        $config = [];
        if (Yii::$app instanceof \yii\web\Application) {

            //Basic app detection
            $module = Yii::getAlias('@common', false) === false ? 'frontend' : basename($basePath);

            if(is_dir(__DIR__ . DIRECTORY_SEPARATOR . $module)){
            //if(in_array($module, ['frontend', 'backend'])){
                $this->setBasePath(__DIR__ . DIRECTORY_SEPARATOR . $module);
                $this->controllerNamespace = __NAMESPACE__ . '\\' . $module . '\\controllers';

                $config = yii\helpers\ArrayHelper::merge(
                    require __DIR__ . '/common/config/main.php',
                    require __DIR__ . 'Module2.php/' . $module . '/config/main.php',
                    [
                        //'components' => $this->components,
                        'params' => $this->params,
                    ]
                );
            }

            $this->on(Module::EVENT_BEFORE_ACTION, function($event){
                $parts = explode('/', $this->getUniqueId());
                $i = 0;
                foreach ($parts as $part){
                    $label = Inflector::camel2words($part);
                    $route = '/' . implode('/', array_slice($parts, 0, $i+1));

                    $this->view->params['breadcrumbs'][] = ['label' => $label, 'url' => [$route]];
                    $i++;
                }
            });

        } elseif (Yii::$app instanceof \yii\console\Application) {

            //Basic app detection
            //$module = Yii::getAlias('@common', false) === false ? 'console' : basename($basePath);
            $module = 'console';

            $this->setBasePath(__DIR__ . DIRECTORY_SEPARATOR . $module);
            $this->controllerNamespace = __NAMESPACE__ . '\\' . $module . '\\controllers';

            $config = yii\helpers\ArrayHelper::merge(
                require __DIR__ . '/common/config/main.php',
                require __DIR__ . 'Module2.php/' . $module . '/config/main.php',
                [
                    //'components' => $this->components,
                    'params' => $this->params,
                ]
            );
        }

        // initialize the module with the configuration loaded
        Yii::configure($this, $config);
    }

    public function set($id, $definition)
    {
        // there is a parent module.
        if (isset($definition, $this->module)) {
            $definitions = $this->module->getComponents(true);

            // definition exist for component id.
            if (isset($definitions[$id])) {
                $component = $definitions[$id];

                $config = $definition;
                if(is_string($config) && is_callable($config, true)){
                    $config = [
                        'class' => $config
                    ];
                }
                // make sure its a array that is not callable and string that is callable
                if (is_array($config) && !is_callable($config, true)) {
                    if (is_object($component) && !($component instanceof \Closure)) {

                        $class = get_class($component);
                        if(isset($config['class'])){
                            $class = $config['class'];
                            unset($config['class']);
                        }

                        if ($component instanceof $class) {
                            $definition = clone $component;
                            Yii::configure($definition, $config);
                        } else {
                            throw new InvalidConfigException("Unexpected configuration type for the \"$id\" component: " . gettype($definition));
                        }
                    } elseif (is_array($component)) {
                        $definition = yii\helpers\ArrayHelper::merge($component, $config);
                    }
                }
            }
        }

        parent::set($id, $definition);

    }
}
