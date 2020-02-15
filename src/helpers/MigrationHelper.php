<?php

namespace phpload\helpers;

use Yii;

/**
 * Migration helper provides all core-columns needed for each 
 * tables.
 * In your migration class:
 *
 * ```php
 * $this->createTable('myTable',ArrayHelper::merge(MigrationHelper::getSystemCols(),['foo' => 'text']));
 * ``` 
 */
final class MigrationHelper extends \yii\db\Migration
{
	public $syscols;

	public static function getSystemCols()
	{
		$self = new static;
		$self->syscols = [
			'id'			=> $self->primaryKey(),
			'pid'			=> $self->integer(),
			'updated_by'	=> $self->integer(),
			'created_by'	=> $self->integer(),
			'created_at'	=> $self->timestamp(6),
			'updated_at'	=> $self->timestamp(6),
			'modelClass'	=> $self->text(),
			'state'			=> $self->string(250),
			'title'			=> $self->string(250),
		];

		return $self->syscols;
	}
}