<?php

/**
 * NO declare ticks here!
 * @see https://stackoverflow.com/questions/49759437/curl-cannot-be-killed-by-a-php-sigint-with-custom-signal-handler
 */
namespace phpload\core\commands;

use Yii;
use yii\console\Controller;
use yii\helpers\ArrayHelper;
use phpload\core\models\DownloadJob;
use phpload\core\models\DownloadItem;
use yii\helpers\Json;
use phpload\core\helpers\Dictionary;

/**
 * test abort
 */
class WorkerController extends Controller
{
	/** 
	 * @param int $downloadItemId Primary Key of DownloadItem
	 * @param int $limitBandwidth in kb/sec, 0 means no limit
	 */
	public function actionIndex($downloadItemId,$limitBandwidth=0)
	{
		pcntl_async_signals(true);

		$config = parse_ini_file("phpload.conf", true);

		$item = DownloadItem::findOne($downloadItemId);

		if (!$item) {
			Yii::error(print_r("Worker PID " . getmypid() . " could not find DownloadItemId " . $downloadItemId,true),__METHOD__);
			exit();
		}

		$client = $item->getDownload($limitBandwidth);
		if (!$client) {
			Yii::error(print_r("no client available!",true),__METHOD__);
			exit();
		}

		$sighandler = function (int $signo,$siginfo) use ($item,$client) {
			$item->updateAttributes(['state' => Dictionary::STATE_PAUSED]);
			exit();
		};

		pcntl_signal(SIGTERM, $sighandler);
		pcntl_signal(SIGINT, $sighandler);

		$item->updateAttributes([
			'state' 	=> Dictionary::STATE_PROC,
			'procid'	=> posix_getpid()
		]);

		$client->send();

		$transaction = DownloadItem::getDb()->beginTransaction();
		try {
			$item->state = Dictionary::STATE_COMPLETED;
			$item->save();
			$transaction->commit();
		} catch (\Exception $e) {
			$transaction->rollback();
		} catch (\Throwable $e) {
			$transaction->rollback();
		}
		rename($item->dest_file, $config['downloads']['downloads'] . '/' . $item->title);
	}	

}