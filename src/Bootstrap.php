<?php

namespace phpload\core;

use Yii;
use yii\base\BootstrapInterface;
use yii\base\Event;
use phpload\core\helpers\Dictionary;

class Bootstrap implements BootstrapInterface
{
	public function bootstrap($app)
	{
		$storeModelClass = function (\yii\base\ModelEvent $event) {
	 		if (
	 			$event->sender->hasAttribute('modelClass')
	 			&& !$event->sender->modelClass
	 		) {
	 			$event->sender->modelClass = get_class($event->sender);
	 		}
	 	};

		/** 
		 * store AR ClassName to database.
		 */
		 Event::on(
		 	\yii\db\ActiveRecord::class,
		 	\yii\db\ActiveRecord::EVENT_BEFORE_INSERT,
		 	$storeModelClass
		);
		Event::on(
		 	\yii\db\ActiveRecord::class,
		 	\yii\db\ActiveRecord::EVENT_BEFORE_UPDATE,
		 	$storeModelClass
		);
	}
}