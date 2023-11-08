<?php


use yii\bootstrap\Html;
use yii\bootstrap\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\Role */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="rule-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'ns') ?>

    <?= $form->field($model, 'className')->textInput() ?>

    <?= $form->field($model, 'baseClass') ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => 64]) ?>

    <?= $form->field($model, 'bizRule', [])->textarea(['rows' => 6]) ?>

    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

