<?php

/**
 * Database
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2007-2024, Amaury Bouchard
 */

namespace Trantor\Exceptions;

/**
 * Exception for database errors.
 */
class Database extends \Exception {
	/** Fundemental error. */
	const FUNDAMENTAL = 0;
	/** Connection error. */
	const CONNECTION = 1;
	/** Query error. */
	const QUERY = 2;
	/** Type error. */
	const TYPE = 3;
}

