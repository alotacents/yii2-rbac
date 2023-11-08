<?php
/**
 * Created by PhpStorm.
 * User: Jensen
 * Date: 7/18/2018
 * Time: 6:19 AM
 */

namespace alotacents\rbac\traits;

use Yii;
use alotacents\rbac\Module;

/**
 * Trait AuthManagerTrait
 * @package alotacents\rbac\traits
 */
trait AuthManagerTrait
{
    /**
     * @return \yii\rbac\CheckAccessInterface|\yii\rbac\ManagerInterface
     */
    public static function getAuthManager()
    {

        if (($module = Module::getInstance()) !== null) {
            $module = Yii::$app;
        }

        $user = $module->getUser();

        return $user !== null && $user->accessChecker !== null ? $user->accessChecker : Yii::$app->getAuthManager();
    }
}