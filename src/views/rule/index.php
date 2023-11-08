<?php

use yii\bootstrap\Html;
use yii\grid\GridView;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $searchModel common\models\PermissionSearch */
/* @var $dataProvider yii\data\ArrayDataProvider */

$this->title = 'Rules';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="rule-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php Pjax::begin(); ?>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?= Html::a('Create Rule', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'class' => 'yii\grid\SerialColumn'
            ],
            'name',
            [
                'class' => 'yii\grid\ActionColumn',
                'buttonOptions' => ['class' => 'btn btn-small']
            ],
        ],
    ]); ?>
    <?php Pjax::end(); ?>
</div>

