<?php
namespace alotacents\rbac\controllers;


use alotacents\rbac\components\AuthBehavior;
use alotacents\rbac\models\AssignmentForm;
use alotacents\rbac\traits\AuthManagerTrait;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\filters\VerbFilter;
use alotacents\rbac\Module;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;

use yii\web\NotFoundHttpException;

use alotacents\rbac\models\Assignment;

class AssignmentController extends Controller
{

    public $userClassName;
    public $idField = 'id';

    public $userColumns = [];
    public $usernameField = 'username';
    public $fullnmeField;

    public function init()
    {
        parent::init();
        //$this->view->params['breadcrumbs'][] = ['label' => 'Assignment', 'url' => ['index'] ];

        if ($this->userClassName === null) {
            $this->userClassName = $this->module->user->identityClass;
        }

    }

    /**
     * Displays a single Assignment model.
     * @param  integer $id
     * @return mixed
     */
    public function actionView($id)
    {

        $authManager = AssignmentForm::getAuthManager();

        $model = $this->findModel($id);

        $assignments = $authManager->getAssignments($model->getUserId());

        /*if(Yii::$app->request->isPost){

            $items = Yii::$app->request->post('items')
            foreach($items as $item){
                $itemObject = $authManager->getRole($item);
                if (!$itemObject) {
                    throw new InvalidParamException("There is no item \"$item\".");
                }

                if($authManager->assign($itemObject, $model->userId)){
                    return $this->redirect(['view', 'id' => $id]);
                }
            }

        }*/

        $userColumns = $this->module->userColumns;
        if(!is_array($userColumns)){
            $userColumns = [];
        }

        return $this->render('view', [
            'model'=>$model,
            'userColumns' => $userColumns,
            'itemList' => ArrayHelper::map(array_merge($authManager->getRoles(), $authManager->getPermissions()), 'name', 'name', 'type'),
        ]);
    }


    public function actionIndex()
    {
        /** @var $model \app\models\User  */
        $model = new $this->userClassName;
        $model->load(Yii::$app->request->get());

        /** @var $query \yii\db\Query */
        $query = $model::find();
//        foreach ($this->module->userAttributes as $attr){
//            $query->andFilterCompare($attr,$model->getAttribute($attr),'LIKE');
//        }

        $dataProvider = new ActiveDataProvider([
           'query' => $query
        ]);

        $userColumns = $this->module->userColumns;
        if(!is_array($userColumns)){
            $userColumns = [];
        }
                
        //$dataProvider->pagination->pageSize = \kak\widgets\grid\GridView::getPaginationSize();

        return $this->render('index', compact('model','dataProvider', 'userColumns') );
    }

    public function actionAssign($id){

        $model = $this->findModel($id);

        try {

            if ($model->load(Yii::$app->request->post()) && $model->apply()) {
                Yii::$app->session->setFlash('success', "Successfully applied assignments");
            } else {
                Yii::$app->session->setFlash('error', "Failed to apply assignments");
            }
            
        } catch (\Throwable $e){
            Yii::$app->session->setFlash('error',"Failed to apply assignments with error message " . $e->getMessage());
        } catch (\Exception $e){
            Yii::$app->session->setFlash('error',"Failed to apply assignments with error message " . $e->getMessage());
        }

        return $this->redirect(['view', 'id' => $model->userId]);

    }

/*
    public function actionAssign($id){

        $authManager = static::getAuthManager();

        $model = $this->findModel($id);

        $assignedRoles = $model->roles;
        $assignedPermissions = $model->permissions;

        try {
            
            if ($model->load(Yii::$app->request->post()) && $model->apply()) {

                try {

                    $methods = [
                        'assign' => [],
                        'revoke' => [],
                    ];

                    $roles = $model->roles;
                    if(isset($roles)) {

                        $remove = array_diff($assignedRoles, $roles);
                        foreach ($remove as $name) {
                            if(($item = $authManager->getRole($name)) !== null) {
                                if($authManager->revoke($item, $model->userId) !== null) {
                                    $methods['assign'][] = $item;
                                }
                            }
                        }

                        $add = array_diff($roles, $assignedRoles);
                        foreach ($add as $name) {
                            if(($item = $authManager->getRole($name)) !== null) {
                                if($authManager->assign($item, $model->userId) !== null) {
                                    $methods['revoke'][] = $item;
                                }
                            }
                        }
                    }

                    $permissions = $model->permissions;
                    if(isset($permissions)) {

                        $remove = array_diff($assignedPermissions, $permissions);
                        foreach ($remove as $name) {
                            if(($item = $authManager->getPermission($name)) !== null) {
                                if($authManager->revoke($item, $model->userId) !== null) {
                                    $methods['assign'][] = $item;
                                }
                            }
                        }

                        $add = array_diff($permissions, $assignedPermissions);
                        foreach ($add as $name) {
                            if(($item = $authManager->getPermission($name)) !== null) {
                                if($authManager->assign($item, $model->userId) !== null) {
                                    $methods['revoke'][] = $item;
                                }
                            }
                        }

                    }

                    Yii::$app->session->setFlash('success', "Successfully applied assignments");
                } catch (\Throwable $e) {
                    foreach ($methods as $method=>$items) {
                        foreach ($items as $item) {
                            $authManager->{$method}($item, $id);
                        }
                    }
                    throw $e;
                } catch (\Exception $e) {
                    foreach ($methods as $method=>$items) {
                        foreach ($items as $item) {
                            $authManager->{$method}($item, $id);
                        }
                    }
                    throw $e;
                }
                    
            }
        } catch (\Throwable $e){
            Yii::$app->session->setFlash('error',"Failed to apply assignments with error message " . $e->getMessage());
        } catch (\Exception $e){
            Yii::$app->session->setFlash('error',"Failed to apply assignments with error message " . $e->getMessage());
        }

        return $this->redirect(['view', 'id' => $model->userId]);
        
    }

    public function actionRevoke($id){

        $model = $this->findModel($id);

        try {

            if (Yii::$app->request->isPost) {
                if (($items = Yii::$app->request->post('items')) !== null) {
                    $items = (array) $items;
                    if (is_array($items) && $model->revoke($items)) {
                        Yii::$app->session->setFlash('success', "Successfully Revoked items " . implode(', ', $items));
                    } else {
                        Yii::$app->session->setFlash('error',"Error failed to Revoked items " . implode(', ', $items));
                    }
                }
            }

        } catch (\Exception $e){
            Yii::$app->session->setFlash('error',"Error Revoke Failed with message " . $e->getMessage());
        } catch (\Throwable $e){
            Yii::$app->session->setFlash('error',"Error Revoke Failed with message " . $e->getMessage());
        }

        return $this->redirect(['view', 'id' => $model->userId]);

    }
*/
    protected function findModel($id)
    {
        if (($model = AssignmentForm::findUser($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }



}