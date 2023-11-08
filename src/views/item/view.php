<?php

use yii\bootstrap\Html;
use yii\widgets\DetailView;
use yii\bootstrap\ActiveForm;
use yii\grid\GridView;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $model common\models\Permission */

$label = \yii\helpers\Inflector::camel2words($this->context->id);

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => $label, 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="item-view">

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
            'name',
            'description',
            'ruleName',
            'data',
        ],
    ]) ?>

    <?php $form = ActiveForm::begin(['action' =>['add', 'id' => $model->name], 'method' => 'post']); ?>

    <?= $form->field($model, 'children', [
        'inputTemplate' => Html::tag('div', '{input}'.Html::tag('span', Html::submitInput('Add Child', ['name'=>'addItem', 'class' => 'btn btn-success']), ['class'=>'input-group-btn']), ['class'=>'input-group']),
    ])->label(false)->dropDownList(\yii\helpers\ArrayHelper::map($availableList,'name','name', 'type'), [
        'groups'=>[
            1=>['label'=>'Roles'],
            2=>['label'=>'Permissions'],
        ],
        'name'=>'children[]',
        'value'=>'',
        'prompt'=>'Select Item',
    ]); ?>

    <?php // Html::submitInput('Add Child', ['name'=>'addItem', 'class' => 'btn btn-success']) ?>

    <?php ActiveForm::end(); ?>

    <div class="row">
        <div class="col-xs-6">

            <?php //Pjax::begin(); ?>

            <?= GridView::widget([
                'dataProvider' => $ancestorDataProvider,
                //'filterModel' => $searchModel,
                'columns' => [
                    [
                        'attribute'=>'name',
                        'format' => 'html',
                        'value'=>function ($data, $key, $index, $column) {
                            $text = Html::encode($data[$column->attribute]);
                            $additionalOptions = ['style' => ['color' => isset($data['level']) && $data['level'] === 1 ? '' : '#333;' ] ];
                            $params = is_array($key) ? $key : ['id' => (string) $key];
                            switch ($data['type']){
                                case \yii\rbac\Item::TYPE_ROLE;
                                    $params[0] = 'role/view';
                                    break;
                                case \yii\rbac\Item::TYPE_PERMISSION;
                                    $params[0] = 'permission/view';
                                    break;
                                default;
                                    $params[0] = '/'.\alotacents\rbac\Module::getInstance()->id.'/';
                            }
                            $options = array_merge([
                                'title' => isset($data['description']) ? $data['description'] : $text,
                                'aria-label' => $text,
                                //'data-pjax' => '0',
                            ], $additionalOptions);
                            //return $key;
                            return Html::a($text, $params, $options);
                        },
                    ],
                    //'description',
                    [
                        'attribute'=>'type',
                        'value'=>function ($data, $key, $index, $column) {
                            isset($data[$column->attribute]) ? \alotacents\rbac\base\ItemForm::getTypeText($data[$column->attribute]) : null;
                        },
                    ],
                    'ruleName' => [
                        'attribute' => 'ruleName',
                        'filter' => [],
                    ],
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'template' => '{remove}',
                        'buttons'=>[
                            'remove' =>function ($url, $data, $key, $index, $column) {
                                $title = 'Remove';
                                $iconName = 'trash';
                                $additionalOptions = [
                                    'data-confirm' => 'Are you sure you want to remove this item?',
                                    'data-method' => 'post',
                                    'data-params' => \common\helpers\ArrayHelper::toQueryArray(['parents' => is_array($key) ? $key : [$key]]),
                                ];
                                $options = array_merge([
                                    'title' => $title,
                                    'aria-label' => $title,
                                    'data-pjax' => '0',
                                ], $additionalOptions, $column->buttonOptions);
                                $icon = Html::tag('span', '', ['class' => "glyphicon glyphicon-$iconName"]);
                                return Html::a($icon, $url, $options);
                            },
                        ],
                        'visibleButtons' => [
                            'remove' => function($data, $key, $index){
                                return isset($data['level']) && $data['level'] === 1;
                            }
                        ],
                        'urlCreator' => function ($action, $data, $key, $index, $column) use ($model) {
                            switch ($action) {
                                case 'remove':
                                    $params = ['id' => (string) $model->name];
                                    break;
                                default:
                                    $params = is_array($key) ? $key : ['id' => (string) $key];
                            }

                            $params[0] = $column->controller ? $column->controller . '/' . $action : $action;

                            return \yii\helpers\Url::toRoute($params);
                        },
                        'buttonOptions' => ['class' => 'btn btn-small']
                    ],
                ],
            ]); ?>
            <?php //Pjax::end(); ?>

        </div>

        <div class="col-xs-6">

            <?php //Pjax::begin(); ?>

            <?= GridView::widget([
                'dataProvider' => $descendantDataProvider,
                //'filterModel' => $searchModel,
                'columns' => [
                    [
                        'attribute'=>'name',
                        'format' => 'html',
                        'value'=>function ($data, $key, $index, $column) {
                            $text = Html::encode($data[$column->attribute]);
                            $additionalOptions = ['style' => ['color' => isset($data['level']) && $data['level'] === 1 ? '' : '#333;' ] ];
                            $params = is_array($key) ? $key : ['id' => (string) $key];
                            switch ($data['type']){
                                case \yii\rbac\Item::TYPE_ROLE;
                                    $params[0] = 'role/view';
                                    break;
                                case \yii\rbac\Item::TYPE_PERMISSION;
                                    $params[0] = 'permission/view';
                                    break;
                                default;
                                    $params[0] = '/'.\alotacents\rbac\Module::getInstance()->id.'/';
                            }
                            $options = array_merge([
                                'title' => isset($data['description']) ? $data['description'] : $text,
                                'aria-label' => $text,
                                //'data-pjax' => '0',
                            ], $additionalOptions);
                            //return $key;
                            return Html::a($text, $params, $options);
                        },
                    ],
                    //'description',
                    [
                        'attribute'=>'type',
                        'value'=>function ($data, $key, $index, $column) {
                            return isset($data[$column->attribute]) ? \alotacents\rbac\base\ItemForm::getTypeText($data[$column->attribute]) : null;
                        },
                    ],
                    'ruleName' => [
                        'attribute' => 'ruleName',
                        'filter' => \alotacents\rbac\base\ItemForm::getTypeList(),
                    ],
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'template' => '{remove}',
                        'buttons'=>[
                            'remove' =>function ($url, $data, $key, $index, $column) {
                                $title = 'Remove';
                                $iconName = 'trash';
                                $additionalOptions = [
                                    'data-confirm' => 'Are you sure you want to remove this item?',
                                    'data-method' => 'post',
                                    'data-params' => \common\helpers\ArrayHelper::toQueryArray(['children' => is_array($key) ? $key : [$key]]),
                                ];
                                $options = array_merge([
                                    'title' => $title,
                                    'aria-label' => $title,
                                    'data-pjax' => '0',
                                ], $additionalOptions, $column->buttonOptions);
                                $icon = Html::tag('span', '', ['class' => "glyphicon glyphicon-$iconName"]);
                                return Html::a($icon, $url, $options);
                            },
                        ],
                        'visibleButtons' => [
                            'remove' => function($data, $key, $index){
                                return isset($data['level']) && $data['level'] === 1;
                            }
                        ],
                        'urlCreator' => function ($action, $data, $key, $index, $column) use ($model) {
                            switch ($action) {
                                case 'remove':
                                    $params = ['id' => (string) $model->name];
                                    break;
                                default:
                                    $params = is_array($key) ? $key : ['id' => (string) $key];
                            }

                            $params[0] = $column->controller ? $column->controller . '/' . $action : $action;

                            return \yii\helpers\Url::toRoute($params);
                        },
                        'buttonOptions' => ['class' => 'btn btn-small']
                    ],
                ],
            ]); ?>
            <?php //Pjax::end(); ?>

        </div>
    </div>


    <?php
 /*
   <div class="row">
        <div class="col-xs-6 col-sm-5">

            <?= $form->field($model, 'available', [])->dropDownList(\yii\helpers\ArrayHelper::map($model->getAvailable(),'name','name', 'type'), [
                'groups'=>[
                    1=>['label'=>'Roles'],
                    2=>['label'=>'Permissions'],
                ],
                'multiple'=>true,
                'size'=>10,
                'value'=>'',
                //'prompt'=>'Select Item',
            ]); ?>

        </div>

        <div class="col-xs-6 col-sm-5 col-sm-push-2">
            <?= $form->field($model, 'children', [])->dropDownList(\yii\helpers\ArrayHelper::map($model->getDescendant(),'name',function($item){
                return str_repeat('-', $item['level']) . $item['name'];
            }), [
                    'options'=>array_map(function($item){
                        $option = [];
                        if($item['level'] > 0){
                            $option['disabled'] = true;
                        }
                        return $option;
                    }, $model->getDescendant()),
                'groups'=>[
                    1=>['label'=>'Roles'],
                    2=>['label'=>'Permissions'],
                ],
                'multiple'=>true,
                'size'=>10,
                'value'=>'',
                //'prompt'=>'Select Item',
            ]); ?>
        </div>

        <div class="col-xs-12 col-sm-2 col-sm-pull-5 text-center">
            <div class="form-group" style="margin-top: 25px;">
                <?= Html::submitInput('← Remove', ['name'=>'removeItem', 'class' => 'btn btn-success']) ?>
                <?= Html::submitInput('Add →', ['name'=>'addItem', 'class' => 'btn btn-success']) ?>
            </div>
        </div>
    </div>
 */ ?>

</div>
