<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Permission */

$label = $model->getTypeText($model->type);

$this->title = "Update {$label}: {$model->name}";
$this->params['breadcrumbs'][] = ['label' => \yii\helpers\Inflector::pluralize($label), 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->name, 'url' => ['view', 'id' => $model->name]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="item-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'rulesList' => $rulesList,
    ]) ?>

</div>
