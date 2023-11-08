<?php

namespace alotacents\rbac\base;

use Yii;
use yii\data\ArrayDataProvider;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Inflector;
use yii\rbac\Item;
use alotacents\rbac\components\AuthBehavior;

use yii\web\NotFoundHttpException;

abstract class ItemController extends Controller
{

    public $modelClass;
    public $searchModelClass;

    public function init(){

        parent::init();

        $this->setViewPath($this->module->getViewPath() . DIRECTORY_SEPARATOR . 'item');
    }

    public function actionIndex()
    {
        $class = $this->searchModelClass;

        $searchModel = new $class();

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        return $this->render('index', [
            'searchModel'=>$searchModel,
            'dataProvider'=>$dataProvider,
        ]);
    }

    public function actionView($id)
    {
        $model = $this->findModel($id);
        /*if (Yii::$app->request->isPost){
            $role = Yii::$app->request->post('Permission');
            if(Yii::$app->request->post('removeItem') !== null && isset($role['children']) && $model->removeChildren($role['children'])){
                return $this->redirect(['view', 'id' => $model->name]);
            } elseif(Yii::$app->request->post('addItem') !== null && isset($role['items']) && $model->addChildren($role['items'])){
                return $this->redirect(['view', 'id' => $model->name]);
            }
        }*/

        $authManager = $model->getAuthManager();
        $authManager->attachBehavior(AuthBehavior::class, AuthBehavior::class);

        $items = $authManager->getPermissions();
        if($model->type === Item::TYPE_ROLE){
            $items += $authManager->getRoles();
        }

        unset($items[$model->name]);

        $assigned = [];

        $children = $authManager->getChildren($model->name);
        foreach($children as $childName=>$child){
            $assigned[$childName] = true;
        }

        $ancestors = $authManager->getAncestor($model->name);
        foreach($ancestors as $ancestor){
            $assigned[$ancestor['name']] = true;
        }

        //$exclude = array_flip( array_merge(array_keys($authManager->getChildren($model->name)), ArrayHelper::getColumn($authManager->getAncestor($model->name), 'name', false)) );
        $available = array_diff_key($items, $assigned);

        $ancestorDataProvider = new ArrayDataProvider([
           'key' => 'name',
           'allModels' => $ancestors,
        ]);

        $descendantDataProvider = new ArrayDataProvider([
            'key' => 'name',
            'allModels' => $authManager->getDescendant($model->name),
        ]);
        $authManager->detachBehavior(AuthBehavior::class);

        return $this->render('view', [
            'model'=>$model,
            'ancestorDataProvider' => $ancestorDataProvider,
            'descendantDataProvider' => $descendantDataProvider,
            'availableList' => $available,
        ]);
    }

    public function actionCreate()
    {
        $authManager = $this->modelClass::getAuthManager();

        $class = $this->modelClass;

        $model = new $class();
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->name]);
        }

        return $this->render('create', [
            'model' => $model,
            'rulesList' => \yii\helpers\ArrayHelper::map($authManager->getRules(),'name','name'),
        ]);
    }


    /**
     * Updates an existing AuthItem model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param  string $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $authManager = $this->modelClass::getAuthManager();

        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->name]);
        }
        return $this->render('update', [
            'model' => $model,
            'rulesList' => \yii\helpers\ArrayHelper::map($authManager->getRules(),'name','name'),
        ]);
    }

    /**
     * Deletes an existing AuthItem model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param  string $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);

        $model->delete();

        return $this->redirect(['index']);
    }

    /**
     * Deletes an existing AuthItem model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param  string $id
     * @return mixed
     */
    public function actionAdd($id)
    {
        $model = $this->findModel($id);

        try {

            if (Yii::$app->request->isPost) {

                try {

                    $methods = [
                        'removeChild' => [],
                        'removeParent' => [],
                    ];

                    if (($children = Yii::$app->request->post('children')) !== null) {

                        if(!is_array($children)){
                            $children = (array) $children;
                        }

                        foreach ($children as $itemName){
                            if($model->addChild($itemName)){
                                $methods['removeChild'][] = itemName;
                            }
                        }

                        Yii::$app->session->addFlash('success', "Successfully added child items " . implode(', ', $children));
                    }

                    if(($parents = Yii::$app->request->post('parents')) !== null) {

                        if(!is_array($parents)){
                            $parents = (array) $parents;
                        }

                        foreach ($parents as $itemName){

                            if($model->addParent($itemName)){
                                $methods['removeParent'][] = itemName;
                            }
                        }

                        Yii::$app->session->addFlash('success', "Successfully added parent items " . implode(', ', $parents));
                    }
                } catch (\Throwable $e) {
                    foreach ($methods as $method=>$items) {
                        foreach ($items as $removeItem) {
                            $model->{$method}($removeItem);
                        }
                    }
                    throw $e;
                } catch (\Exception $e){
                    foreach ($methods as $method=>$items) {
                        foreach ($items as $removeItem) {
                            $model->{$method}($removeItem);
                        }
                    }
                    throw $e;
                }
            }

        } catch (\Throwable $e){
            Yii::$app->session->setFlash('error',"Error Add Failed with message " . $e->getMessage());
        } catch (\Exception $e){
            Yii::$app->session->setFlash('error',"Error Add Failed with message " . $e->getMessage());
        }

        return $this->redirect(['view', 'id' => $model->name]);
    }

    /**
     * Deletes an existing AuthItem model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param  string $id
     * @return mixed
     */
    public function actionRemove($id)
    {
        $model = $this->findModel($id);

        try {

            if (Yii::$app->request->isPost) {

                try {

                    $methods = [
                        'addChild' => [],
                        'addParent' => [],
                    ];

                    if (($children = Yii::$app->request->post('children')) !== null) {

                        if(!is_array($children)){
                            $children = (array) $children;
                        }

                        foreach ($children as $itemName){
                            if($model->removeChild($itemName)){
                                $methods['addChild'][] = itemName;
                            }
                        }

                        Yii::$app->session->addFlash('success', "Successfully removed child items " . implode(', ', $children));
                    }

                    if(($parents = Yii::$app->request->post('parents')) !== null) {

                        if(!is_array($parents)){
                            $parents = (array) $parents;
                        }

                        foreach ($children as $itemName){
                            if($model->removeParent($itemName)){
                                $methods['addParent'][] = itemName;
                            }
                        }

                        Yii::$app->session->addFlash('success', "Successfully removed parent items " . implode(', ', $parents));
                    }
                } catch (\Throwable $e) {
                    foreach ($methods as $method=>$items) {
                        foreach ($items as $removeItem) {
                            $model->{$method}($removeItem);
                        }
                    }
                    throw $e;
                } catch (\Exception $e){
                    foreach ($methods as $method=>$items) {
                        foreach ($items as $removeItem) {
                            $model->{$method}($removeItem);
                        }
                    }
                    throw $e;
                }
            }

        } catch (\Exception $e){
            Yii::$app->session->setFlash('error',"Error Remove Failed with message " . $e->getMessage());
        } catch (\Throwable $e){
            Yii::$app->session->setFlash('error',"Error Remove Failed with message " . $e->getMessage());
        }

        return $this->redirect(['view', 'id' => $model->name]);
    }

    /**
     * Finds the User model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return Role the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        $class = $this->modelClass;
        if (($model = $class::findItem($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

}
