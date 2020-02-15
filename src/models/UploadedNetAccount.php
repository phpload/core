<?php

declare (strict_types=1);

namespace phpload\models;

use Yii;
use yii\httpclient\Client;
use yii\helpers\ArrayHelper;
use phpload\models\PremiumAccount;
use phpload\interfaces\PremiumAccountInterface;

final class UploadedNetAccount extends PremiumAccount implements PremiumAccountInterface
{
	const LOGIN_URL = 'http://uploaded.net/io/login';

	private $cookies = [];

	private $contentlength;
	private $filename;

	
	/**
	 * @var string the url to the download target
	 * Differs from Links inner a DLC:
	 * The Links in a DLC points to a Webform with a Button "Download"
	 * The Formaction provides the concret download server url
	 */
	public $url;


	public function setCookies(array $cookies): PremiumAccountInterface
	{
		$this->cookies = $cookies;

		return $this;
	}

	public function getCookies(): array
	{
		if (!$this->username || !$this->password) {
			throw new \yii\base\InvalidConfigException("Eihter username nore password are set.");
		}

		if (!$this->cookies) {
			$response = (new Client())->post(self::LOGIN_URL,[
				'id' => $this->username,
				'pw' => $this->password
			])->send();

			$this->cookies = $response->getCookies();
		}

		return $this->cookies->toArray();
	}

	public function setLink(string $link)
	{

		Yii::trace("resolve DLC Item  " . print_r($link,true),__METHOD__);

		$this->url = $this->resolveUrlByLink($link);

		Yii::error("URL is " . print_r($this->url,true),__METHOD__);

		if (!$this->url) {
			throw new \yii\base\InvalidConfigException("url is null");
		}

	}

	/**
	 * resolve the url by given Downloadlink from DLC
	 *
	 * @param string $link the Link from a DLC
	 *
	 * @return string|null the url or null if no url could be found
	 */
	private function resolveUrlByLink(string $link): string
	{
		$response  = (new Client())
			->get($link)
			->setCookies($this->getCookies());

		$response = $response->send();

		$doc = new \DOMDocument();
		$doc->loadHTML($response->getContent(),LIBXML_NOWARNING | LIBXML_NOERROR);
		$form = $doc->getElementsByTagName("form");

		if (!isset ($form[0])) {
			return null;
		}

		$link = $form[0]->getAttribute('action');

		if (preg_match("/\/\/uploaded/", $link)) {
			Yii::error(print_r($this->getCookies(),true),__METHOD__);
		}

		return $link ?? null;
	}

	public function download(Client $client, $filehandler): bool
	{
		if (!$this->url) {
			Yii::error(print_r("NO URL, abort",true),__METHOD__);
			return false;
		}

		$response = $client->createRequest()
			->setMethod('GET')
			->setUrl($this->url)
			->setOutputFile($filehandler)
			->setCookies($this->cookies)
			->send();

		return true;
	}

	public function getContentlength(): int
	{
		if (!$this->contentlength) {

			Yii::error("LINK IS " . print_r($this->getUrl(),true),__METHOD__);

			$this->setFileheader($this->getUrl());	
		}

		return (int) $this->contentlength;
	}

	public function isLoggedIn(): bool
	{

	}

	public function getFilename(): string
	{
		if (!$this->filename) {
			$this->setFileheader($this->url);
		}

		return $this->filename;
	}

	private function setFileheader($url)
	{
		$head = (new Client())
			->head($url)
			->setCookies($this->getCookies())
			->send();

		$headers = $head->getHeaders();

		preg_match(
			'/filename=\"([\w\-\.\/]+)\"/',
			$headers->get('content-disposition'),
			$matches
		);

		$this->contentlength = $headers->get('content-length');
		$this->filename = ArrayHelper::getValue($matches,1);
	}

	public function getUrl(): string
	{
		return $this->url;
	}

	public function setUrl($url): UploadedNetAccount
	{
		$this->url = $url;
		return $this;
	}

}