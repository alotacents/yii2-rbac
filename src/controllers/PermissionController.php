<?php

namespace alotacents\rbac\controllers;

use alotacents\rbac\base\ItemController;
use Yii;
use alotacents\rbac\models\Permission;
use alotacents\rbac\models\PermissionSearch;


/**
 * Class PermissionController
 * @package alotacents\rbac\controllers
 */
class PermissionController extends ItemController
{
    /**
     * @var string
     */
    public $modelClass = Permission::class;

    /**
     * @var string
     */
    public $searchModelClass = PermissionSearch::class;

}
