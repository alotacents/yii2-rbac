<?php

use yii\bootstrap\Html;
use yii\widgets\DetailView;
use yii\bootstrap\ActiveForm;
use yii\grid\GridView;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $model common\models\Permission */

$this->title = $model->userId;

$this->params['breadcrumbs'][] = ['label' => 'Assignments', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="assignment-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= DetailView::widget([
        'model' => $model->user,
        'attributes' => $userColumns,
    ]) ?>

    <?php //Pjax::end(); ?>

    <?php $form = ActiveForm::begin(['action' =>['assign', 'id' => $model->userId], 'method' => 'post']); ?>

    <?= $form->field($model, 'roles')->dropDownList($itemList[\yii\rbac\Item::TYPE_ROLE], [
        'multiple'=>true,
        //'size'=>10,
        'prompt'=>'Select Item',
    ]); ?>

    <?= $form->field($model, 'permissions')->checkboxList($itemList[\yii\rbac\Item::TYPE_PERMISSION], [
            //'name'=>'assignment[]',
            //'value'=>array_keys($perm),
            'itemOptions' => [
            ],
            'item' => function ($index, $label, $name, $checked, $value) use ($model) {
                $itemOptions = [
                    'labelOptions' => ['class' => 'checkbox-inline']
                ];
                $itemOptions['disabled'] = in_array($value, $model->getInheritedPermissions());
                $options = array_merge([
                    'label' => $encode ? Html::encode($label) : $label,
                    'value' => $value
                ], $itemOptions);
                return '<div class="checkbox">' . Html::checkbox($name, $checked, $options) . '</div>';
            },
        ]
    ) ?>

    <?php // Html::submitInput('Add Child', ['name'=>'addItem', 'class' => 'btn btn-success']) ?>

    <div class="form-group">
        <?= Html::submitButton('Apply', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>