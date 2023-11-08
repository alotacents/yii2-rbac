<?php

namespace alotacents\rbac\models;

use Yii;
use yii\base\Model;
use yii\data\ArrayDataProvider;

/**
 * UserSearch represents the model behind the search form of `common\models\User`.
 */
class RoleSearch extends Role
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name'], 'match', 'pattern' => '/^[a-z0-9-_а-я]+/iu'],
            [['type'], 'integer'],
            [['name', 'description', 'ruleName', 'data'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {

        $authManager = static::getAuthManager();

        $items = $authManager->getRoles();

        if ($this->load($params) && $this->validate()) {

            $filter = array_filter($this->getAttributes(), function($value){
                return !($value === '' || $value === [] || $value === null || is_string($value) && trim($value) === '');
            });

            if($filter !== []){
                $items = array_values(
                    array_filter($items, function ($item) use ($filter) {
                        $valid = true;

                        foreach($filter as $name => $value){
                            if(stripos($item->{$name}, $value) === false) {
                                $valid = false;
                                break;
                            }
                        }

                        return $valid;
                    })
                );
            }
        }

        return new ArrayDataProvider([
            'allModels' => $items,
        ]);
    }

}
