<?php

/**
 * Cache
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2023, Amaury Bouchard
 * @link	https://www.trantor.org/en/documentation/helper-cli_cache
 */

use \Trantor\Utils\Ansi as TµAnsi;

/**
 * Cache management CLI controller.
 */
class Cache extends \Trantor\Web\Controller {
	/**
	 * Clear the cache.
	 * @param	string	$datasource	Name of the data source to purge. (defaults to "cache")
	 */
	public function clear(string $datasource='cache') {
		$cache = $this->$datasource;
		if (!$cache) {
			print(TµAnsi::color('red', "No '$datasource' data source.\n"));
			exit(1);
		}
		$cache->flush();
		print(TµAnsi::color('green', "Cache cleared.\n"));
	}
}

