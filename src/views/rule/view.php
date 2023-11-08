<?php

use yii\bootstrap\Html;
use yii\widgets\DetailView;
use yii\bootstrap\ActiveForm;
use yii\grid\GridView;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $model common\models\Rule */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Rule', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="rule-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Update', ['update', 'id' => $model->name], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Delete', ['delete', 'id' => $model->name], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Are you sure you want to delete this item?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'ns',
            'className',
            'name',
            'bizRule',
        ],
    ]) ?>


</div>
