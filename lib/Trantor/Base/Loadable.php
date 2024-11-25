<?php

/**
 * Loadable
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2019-2024, Amaury Bouchard
 */

namespace Trantor\Base;

/**
 * Interface for objects that could be automatically loaded by Trantor's dependency injection component (\Trantor\Base\Loader).
 */
interface Loadable {
	/**
	 * Constructor.
	 * @param	\Trantor\Base\Loader	$loader	The dependency injection object.
	 */
	public function __construct(\Trantor\Base\Loader $loader);
}
