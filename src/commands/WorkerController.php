<?php

declare (ticks=1);

namespace phpload\commands;

use Yii;
use yii\console\Controller;
use yii\helpers\ArrayHelper;
use phpload\models\DownloadJob;
use phpload\models\DownloadItem;
use yii\helpers\Json;
use phpload\helpers\Dictionary;

/**
 * test abort
 */
class WorkerController extends Controller
{
	/** 
	 * @param int $jobId PK of DownloadJob
	 */
	public function actionIndex($jobId)
	{
		$job = DownloadJob::findOne($jobId);

		if (!$jobId) {
			Yii::error(print_r("$download " . getmypid() . " could not find Job Id " . $jobId,true),__METHOD__);
			exit();
		}

		$download = $job->getItems()->andWhere([
			'state' => Dictionary::STATE_PENDING,
		])->one();

		if (!$download) {
			Yii::error(print_r("$download " . getmypid() . " could not find pending DownloadItems for jobid " . $jobId,true),__METHOD__);
			exit();
		}

		$sighandler = function (int $signo,$siginfo) use ($download) {
			$download->updateAttributes(['state' => Dictionary::STATE_PAUSED]);
			exit();
		};

		pcntl_signal(SIGTERM, $sighandler);
		pcntl_signal(SIGINT, $sighandler);

		$download->updateAttributes([
			'state' 	=> Dictionary::STATE_PROC,
			'procid'	=> posix_getpid()
		]);

		$download->download();
		$download->updateAttributes(['state' => Dictionary::STATE_COMPLETED]);

		rename($download->dest_file, $job->destination . '/' . $download->title);

	}	

}