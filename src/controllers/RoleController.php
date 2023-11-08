<?php

namespace alotacents\rbac\controllers;

use alotacents\rbac\base\ItemController;
use Yii;
use alotacents\rbac\models\Role;
use alotacents\rbac\models\RoleSearch;

/**
 * Class RoleController
 * @package alotacents\rbac\controllers
 */
class RoleController extends ItemController
{
    /**
     * @var string
     */
    public $modelClass = Role::class;

    /**
     * @var string
     */
    public $searchModelClass = RoleSearch::class;

}
