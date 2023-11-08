<?php

use Yii;
use yii\bootstrap\NavBar;
use yii\bootstrap\Nav;
use yii\helpers\Html;

$controllerName = $this->context->id;
?>

<?php //$this->beginContent('@alotacents/rbac/views/layouts/main.php') ?>
<?php $this->beginContent($this->context->module->getViewPath() . DIRECTORY_SEPARATOR . 'layouts/main.php') ?>

<?= Nav::widget([
    'items' => [
        [
            'label' => 'Assignments',
            'url' => ['assignment/index'],
            'active' =>  $controllerName === 'assignment',
        ],
        [
            'label' => 'Roles',
            'url' => ['role/index'],
            'active' =>  $controllerName === 'role',
        ],
        [
            'label' => 'Permissions',
            'url' => ['permission/index'],
            'active' =>  $controllerName === 'permission',
        ],
        [
            'label' => 'Rules',
            'url' => ['rule/index'],
            'active' =>  $controllerName === 'rule',
        ],
    ],
    'options' => ['class' =>'nav-justified nav-tabs'], // set this to nav-tabs to get tab-styled navigation
]);
?>

<?= $content ?>

<?php $this->endContent(); ?>
