<?php

declare (strict_types=1);

namespace phpload\core\models;

use Yii;
use yii\helpers\Json;

class PremiumAccount extends \yii\db\ActiveRecord
{

	public static function tableName()
	{
		return 'och_accounts';
	}

    public static function instantiate($row)
    {
    	if (
    		isset($row['modelClass'])
    		&& class_exists($row['modelClass'])
    	) {
    		return new $row['modelClass'];
    	}

        return new static();
    }


	public function rules()
	{
		return [
			[['username','password'],'required'],
		];
	}

	public function afterFind()
	{
		if ($this->authCookies) {
			$this->authCookies = Json::decode($this->authCookies);
		}

		return parent::afterFind();
	}

	public function beforeSave($insert)
	{
		$format = Yii::$app->dateFormat;
		$dt = new \DateTime();

		$this->authCookies = Json::encode($this->getCookies());
		$this->authCookiesValidTill = $dt->modify('+2 hour')->format($format);

		return parent::beforeSave($insert);
	}
		
	public static function find()
	{
		$query = parent::find();
		$query->where(['modelClass' => static::class]);
		return $query;
	}

}