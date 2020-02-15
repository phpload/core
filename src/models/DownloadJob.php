<?php

declare (strict_types=1);

namespace phpload\models;

use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\httpclient\Client;
use phpload\interfaces\PremiumAccountInterface;
use phpload\traits\EnsureTrait;
use phpload\helpers\Dictionary;

class DownloadJob extends \yii\db\ActiveRecord
{
	use EnsureTrait;

	/**
	 * @var PremiumAccountInterface
	 */
	private $account;

	public $destination;

	/** 
	 * crypted DLC String
	 */
	public $dlc;

	public static function tableName()
	{
		return 'dl_jobs';
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

	public function getItems()
	{
		return $this->hasMany(DownloadItem::class, [
			'pid' => 'id'
		]);
	}

	public function rules()
	{
		return [
			['dlc','required'],
			['dlc','decryptDlc']
		];
	}

	public function beforeSave($insert)
	{	
		$this->accountClass = get_class($this->account);
		$this->accountId 	= $this->account->getPrimaryKey();
		$this->decrypted_dlc = Json::encode($this->decrypted_dlc);

		return parent::beforeSave($insert);
	}

	public function afterFind()
	{
		if ($this->decrypted_dlc) {
			$this->decrypted_dlc = Json::decode($this->decrypted_dlc);
		}

		return parent::afterFind();
	}

	public function decryptDlc()
	{
		$response = (new Client())->post('http://dcrypt.it/decrypt/paste',[
			'content' => $this->dlc
		])->send();

		if (!($this->decrypted_dlc = ArrayHelper::getValue(
			$response->getData(),
			'success.links'
		))) {
			$this->addError('dlc', 'cant decrypt dlc.');
			return;
		}

		if (!$this->decrypted_dlc) {
			$this->addError('dlc','dlc is empty and contains no links');
		}
	}

	/**
	 * create a Runner for each item in DLC
	 * State of runner is pending
	 *
	 * All Items get the Same filename???
	 */
	public function afterSave($insert,$changedAttributes)
	{
		if (!$insert) {
			return parent::afterSave($insert,$changedAttributes);		
		}

		$dlc = Json::decode($this->decrypted_dlc);
		$filesize = 0;

		foreach ($dlc as $link) {

			$dl = DownloadItem::getInstance($this,$link);
			
			if (!$dl->save()) {
				Yii::error(print_r($dl->getErrors(),true),__METHOD__);
			}
			$filesize += $dl->size_bytes;
			
		}

		$this->updateAttributes(['size_bytes' => $filesize]);

		return parent::afterSave($insert,$changedAttributes);
	}

	/**
	 * update Runners on DB with STATE_PROC and 
	 * no pid on OS present
	 */	
	public function cleanUp()
	{
		$procItems = $this->getItems()
			->andWhere(['state' => Dictionary::STATE_PROC])
			->all();

		foreach ($procItems as $item) {
			if (!posix_getpgid($item->procid)) {
				$item->updateAttributes([
					'state' => Dictionary::STATE_PENDING,
					'procid' => null
				]);
			}
		}
	}

	public function getProcessedBytes()
	{
		return filesize($this->dest_file);
	}


}	