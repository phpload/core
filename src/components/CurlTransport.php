<?php

namespace phpload\components;

use Yii;
use yii\httpclient\CurlTransport as YiiCurlTransport;
use phpload\models\DownloadJob;

class CurlTransport extends YiiCurlTransport
{
	/**
	 * @var phpload\models\DownloadJob current job this Transport belongs to.
	 */
	private $job;

	protected $startTime;
	protected $prevTime;
	protected $prevSize;

	public function setJob(DownloadJob $job)
	{
		$this->job = $job;
	}

	public function getJob(): DownloadJob
	{
		return $this->job;
	}

	protected function prepare($request)
	{
		$this->startTime = $this->prevTime = microtime(true);
		$this->prevSize = 0;
		$curl_options = parent::prepare($request);
		$curl_options[CURLOPT_NOPROGRESS] = false;
		$curl_options[CURLOPT_RETURNTRANSFER] = true;
		$curl_options[CURLOPT_PROGRESSFUNCTION] = function (
			$resource,
			$download_size, 
			$downloaded,
			$upload_size,
			$uploaded
		)  {

			$currentSpeed = 0;
			$averageSpeed = 0;
			if ($downloaded > 0) {
				$averageSpeed = $downloaded / (microtime(true) - $this->startTime);
				$currentSpeed = ($downloaded - $this->prevSize) / (microtime(true) - $this->prevTime);
				$timeRemaining = ($downloaded - $download_size) / $averageSpeed;
				$percentage = sprintf("%.2f", $downloaded*100/$download_size);
				/*
				$this->getJob()->updateAttributes([
					'size_bytes' => $download_size,
					'downloaded_bytes' => $downloaded,
					'avg_speed' => $averageSpeed,
					'current_speed' => $currentSpeed,
					'time_remaining' => $timeRemaining
				]);
				*/
			}

			$this->prevTime = microtime(true);
			$this->prevSize = $downloaded;
				
		};

		return $curl_options;
	}
}