<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $searchModel common\models\PermissionSearch */
/* @var $dataProvider yii\data\ArrayDataProvider */

$label = \yii\helpers\Inflector::camel2words($this->context->id);

$this->title = \yii\helpers\Inflector::pluralize($label);
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="item-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php Pjax::begin(); ?>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?= Html::a("Create {$label}", ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'class' => 'yii\grid\SerialColumn'
            ],
            'name',
            'description',
            'ruleName' => [
                'attribute' => 'ruleName',
                'filter' => [],
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'buttonOptions' => ['class' => 'btn btn-small']
            ],
        ],
    ]); ?>
    <?php Pjax::end(); ?>
</div>
