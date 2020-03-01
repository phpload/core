<?php

declare (strict_types=1);

namespace phpload\core\traits;

use phpload\core\interfaces\PremiumAccountInterface;

trait ApplicationTrait
{
	public $dateFormat = 'Y-m-d H:i:s';

	private $accounts = [];

	public function addAccount(PremiumAccountInterface $acc)
	{
		$this->accounts[] = $acc;
	}

	public function getAccounts()
	{
		return $this->accounts;
	}
}