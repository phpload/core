<?php

declare (strict_types=1);

namespace phpload\interfaces;

interface PremiumAccountInterface
{
	/**
	 * Get the auth-cookies
	 *
	 * @return array Request-Cookies that validates a request as logged in
	 */
	public function getCookies(): array;

	/**
	 * set the auth-cookies
	 * By doing so, multiple downloads dont need a seperate auth.
	 */
	public function setCookies(array $cookies): PremiumAccountInterface;

	/** 
	 * This method will be called after a new Instance of this interface was created
	 *
	 * @param string $link the downloadlink from DLC
	 */
	public function setLink(string $link);

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
	 */
	public function download(\yii\httpclient\Client $client,$filehandler): bool;

	/** 
	 * get the URL to the file to download
	 */
	public function getUrl(): ?string;

}

