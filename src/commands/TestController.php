<?php

namespace phpload\commands;

use Yii;
use yii\console\Controller;
use phpload\models\DownloadJob;
use phpload\models\UploadedNetAccount;

class TestController extends Controller
{
	public function actionIndex()
	{
		$account = UploadedNetAccount::findOne(1);
		if (!$account) {
			$account = new UploadedNetAccount([
				'username' => '15624760',
				'password' => 'qcvaq83k'
			]);
			$account->save();
		}
		
		/**
		 * DownloadJob: Represents a whole DLC (pid=0)
		 */

		$job = DownloadJob::find()->one();
		if (!$job) {
			$job = new DownloadJob([
				'account' => $account,
				'destination' => '/var/www',
				'dlc' => file_get_contents('/home/henry/Downloads/f88e4182d01ed35dc71cf2c1d0dc8f79c5a4bf6e.dlc')
			]);
			if (!$job->save()) {
				Yii::error(print_r($job->getErrors(),true),__METHOD__);
			}
		}

		Yii::error(print_r("want 1 forks ..." ,true),__METHOD__);
		$job->download(1);

	}
}