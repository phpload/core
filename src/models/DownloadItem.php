<?php

declare (strict_types=1);

namespace phpload\models;

use Yii;
use yii\httpclient\Client;
use phpload\interfaces\PremiumAccountInterface;
use phpload\traits\EnsureTrait;
use phpload\helpers\Dictionary;

class DownloadItem extends \yii\db\ActiveRecord
{
	use EnsureTrait;

	/**
	 * @var Link from DLC
	 */
	private $link;

	private $job; 

	private $client;

	public static function tableName()
	{
		return 'dl_jobs';
	}

	public function getJob(): DownloadJob
	{
		return $this->job ?? $this->hasOne(DownloadJob::class, [
			'id' => 'pid'
		])->one();
	}

	public function setJob(DownloadJob $job)
	{
		$this->job = $job;
	}

	public function setLink(string $link)
	{
		$this->link = $link;
	}

	public function getLink(): string
	{
		return $this->link;
	}

	public function beforeSave($insert)
	{	
		$account = $this->job->account;
		$account->setLink($this->link);

		$tempFileName = Yii::$app->security->generateRandomString(15);

		$this->dest_file 	= $this->job->destination . '/' . $tempFileName;
		$this->source_url 	= $account->getUrl();
		$this->size_bytes 	= $account->getContentlength();
		$this->title 		= $account->getFilename();

		return parent::beforeSave($insert);
	}

	public static function getInstance(DownloadJob $job, string $link): DownloadItem
	{
		return new static([
			'pid'  => $job->getPrimaryKey(),
			'link' =>  $link,
			'state' => Dictionary::STATE_PENDING,
			'job'  => $job
		]);
	}

	public function getClient()
	{
		if (!$this->client) {
			$this->client = new Client([
				'transport' => [
					'class' => 'yii\httpclient\CurlTransport',
					'requestConfig' => [
						'options' => [
							/** Detect Filesize and resume Download **/
							CURLOPT_RESUME_FROM => -1
						]
					]
				]
			]);
		}

		return $this->client;
	}

	public function download()
	{
		$this->ensurePk($this);
	
		$fh = fopen($this->dest_file,'w');
		Yii::error(print_r("Download to " . $this->dest_file,true),__METHOD__);
		$this->getJob()
			->account
			->setUrl($this->source_url)
			->download($this->getClient(),$fh)
		;
	}
} 
