<?php

declare (strict_types=1);

namespace phpload\models;

use Yii;

abstract class PremiumAccount extends \yii\db\ActiveRecord
{

	public static function tableName()
	{
		return 'och_accounts';
	}

	public function rules()
	{
		return [
			[['username','password'],'required'],
		];
	}

}