<?php

declare (strict_types=1);

namespace phpload\core\traits;

trait EnsureTrait
{
	private function ensurePk($model)
	{
		if ($model->isNewRecord) {
			throw new \yii\base\InvalidConfigException(get_class($model) . ' must not be new Record!');
		}
	}
}