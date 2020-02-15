<?php

namespace phpload\helpers;

use Yii;

final class Dictionary
{
	const STATE_PENDING = 'pending';
	const STATE_PROC = 'processing';
	const STATE_COMPLETED = 'completed';
	const STATE_ERROR = 'error';
	const STATE_PAUSED = 'paused';
}