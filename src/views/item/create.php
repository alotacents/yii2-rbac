<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\User */

$label = \yii\helpers\Inflector::camel2words($this->context->id);

$this->title = "Create {$label}";
$this->params['breadcrumbs'][] = ['label' => \yii\helpers\Inflector::pluralize($label), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="item-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'rulesList' => $rulesList,
    ]) ?>

</div>
