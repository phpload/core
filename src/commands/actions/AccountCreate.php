<?php

namespace phpload\core\commands\actions;

use Yii;
use yii\console\widgets\Table;
use yii\helpers\BaseConsole;

class AccountCreate extends \yii\base\Action
{
	
	public function run()
	{
		$accounts = Yii::$app->getAccounts();
		$key = $this->prompt($accounts);
		
		$account = $accounts[$key];

		echo $account->getTitle() . "\n\n";

		if ($account::find()->count()) 	{
	        
			if (!$this->controller->confirm($account->getTitle() . ' account exists allready, delete?')) {
		        echo "Abort.\n";
		        return 1;
			}

			foreach ($account::find()->all() as $acc) {
				$acc->delete();
			}

		}

		$account->promptCredentials();

		if (!$account->save()) {
			print_r($account->getErrors());
			return 1;
		}

		echo "Account created!\n";

		return 0;
	}

	public function prompt(array $accounts)
	{
		foreach ($accounts as $key => $account) {
			echo $key . " - " . $account->getTitle() . "\n";
		}

		$key = BaseConsole::input("select a account: ");

		if (!isset ($accounts[$key])) {
			echo "Account does not exists!\n";
			$this->prompt($accounts);
		}

		return $key;
	}

}