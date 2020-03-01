<?php

declare (strict_types=1);

namespace phpload\core\interfaces;

use yii\httpclient\{Client,Request};

interface PremiumAccountInterface
{

	/**
	 * The Title
	 */
	public function getTitle(): string;

	/**
	 * Get the auth-cookies
	 *
	 * @return array Request-Cookies that validates a request as logged in
	 */
	public function getCookies(): array;

	/** 
	 * Resolve Downloadlink by given link from DLC.
	 * Some OCH's generate a Downloadtickt after you follow
	 * a DLC Link. This Method let you handel such scenarios.
	 *
	 * @param string $link the downloadlink from DLC
	 */
	public function setUrl(string $link): self;

	/** 
	 * get the URL to the file to download
	 */
	public function getUrl(): ?string;

	/**
	 * get the filesize in bytes
	 */
	public function getContentLength(): int;

	/**
	 * get the filename
	 */
	public function getFilename(): string;

	/**
	 * @param yii\httpclient\Client $client the yii2 httpclient Curl Transport Stream
	 * @param fopen() resource $filehandler the fileresource to store the download
	 *
	 * @return Request|null
	 */
	public function download(Client $client,$filehandler): ?Request;

	/**
	 * Probe for responsibility.
	 * To find out, which PremiumAccount should be used for Download,
	 * the phpload\core\models\DownloadItem::resolveAccountByLink() will
	 * probe each installed PremiumAccount with the Downloadlink.
	 * If a PremiumAccount thinks, it should be responsible for a given link, return true.
	 *
	 * @param string $link the download url
	 *
	 * @return bool
	 */
	public function probeResponsibility(string $link): bool;

}

