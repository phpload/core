<?php

namespace phpload\core\commands\actions;

use Yii;
use yii\console\widgets\Table;

class AccountIndex extends \yii\base\Action
{
	
	public function run()
	{
		$rows = [];
		foreach (Yii::$app->getAccounts() as $account) {
			$rows[] = [
				$account->getTitle(),
				(($model = $account::find()->one()) ? $model->getPrimaryKey() : 'not configured yet')
			];
		}

        echo Table::widget([
            'headers' => ['Title','config id'],
            'rows' => $rows
        ]);
	}

}