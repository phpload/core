<?php

declare (strict_types=1);

namespace phpload\core\models;

use Yii;
use yii\httpclient\Client;
use phpload\core\interfaces\PremiumAccountInterface;
use phpload\core\traits\EnsureTrait;
use phpload\core\helpers\Dictionary;
use yii\httpclient\Request;

class DownloadItem extends \yii\db\ActiveRecord
{
	use EnsureTrait;

	/**
	 * @var Link from DLC
	 */
	private $link;

	private $job; 

	private $client;

	/**
	 * @var PremiumAccountInterface
	 */
	private $account;

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

	public function setAccount(PremiumAccountInterface $account)
	{
		$this->ensurePk($account);		
		$this->account = $account;
	}

	public function getAccount(): PremiumAccountInterface
	{
		return $this->account ?? $this->hasOne($this->accountClass, [
			'id' => 'accountId'
		])->one();
	}

	public function beforeSave($insert)
	{	
		$accountProbe = $this->resolveAccountByLink($this->link);

		if (!$accountProbe) {
			echo 'No Premium Account found for Link ' . $this->link;
			return false;
		}

		$account = $accountProbe::find()->one();

		if (!$account) {
			echo $accountProbe->getTitle() . " is not configured. Cant Download!\n";
			return false;
		}

		$account->setUrl($this->link);

		$this->accountClass = get_class($account);
		$this->accountId 	= $account->getPrimaryKey();

		if (!$account->getUrl()) {
			echo $this->getJob()->id . " - " . $this->link . " is offline\n";
			$this->state = Dictionary::STATE_OFFLINE;
			return parent::beforeSave($insert);
		}

		$tempFileName = Yii::$app->security->generateRandomString(15);

		$this->dest_file 	= $this->job->destination . '/' . $tempFileName;
		$this->source_url 	= $this->link;
		$this->size_bytes 	= $account->getContentlength();
		$this->title 		= $account->getFilename();

		return parent::beforeSave($insert);
	}

	/**
	 * iterate over all registered PremiumAccounts and 
	 * probe the url.
	 *
	 * @param string $link the url to the Dowloadsource
	 *
	 * @return PremiumAccountInterface|null
	 */
	protected function resolveAccountByLink($link): ?PremiumAccountInterface
	{
		foreach (Yii::$app->getAccounts() as $account) {

			if ($account->probeResponsibility($link)) {
				return $account;
			}
		}

		return null;
	}


	public static function getInstance(DownloadJob $job, string $link): DownloadItem
	{
		$model = new static([
			'pid'  => $job->getPrimaryKey(),
			'link' =>  $link,
			'state' => Dictionary::STATE_PENDING,
			'job'  => $job
		]);

		return $model;
	}

	public function getDownload($limitBandwidth=0): ?Request
	{
		$this->ensurePk($this);

		$offset = 0;
		if (file_exists($this->dest_file)) {
			$offset = filesize($this->dest_file);
			$fh = fopen($this->dest_file,'r+');

			if ($offset > 0) {
				fseek($fh, 1,SEEK_END);
			}
		} else {
			$fh = fopen($this->dest_file,'w+');
		}
				
		$client = new Client([
			
			'requestConfig' => [
				'options' => [
					// Detect Filesize and resume Download
					CURLOPT_RESUME_FROM => $offset,
					CURLOPT_MAX_RECV_SPEED_LARGE => $limitBandwidth*1024,
					CURLOPT_NOPROGRESS => false,
					CURLOPT_PROGRESSFUNCTION => function () {},
				]
			],
			
			'transport' => [
				'class' => 'yii\httpclient\CurlTransport',

			]
		]);

		return $this->getAccount()
			->setUrl($this->source_url)
			->download($client,$fh)
		;
	}
} 
