<?php

/**
 * Plugin
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2019-2024, Amaury Bouchard
 */

namespace Trantor\Web;

/**
 * Basic object for plugin management.
 */
class Plugin extends \Trantor\Web\Controller {
	/**
	 * Method called only when the plugin is executed as pre-plugin.
	 * The return could be an constant (e.g. `return self::EXEC_QUIT;`) or null (`return (null);`) or nothing (`return;`).
	 * Null and zero return values are the same than returning `self::EXEC_FORWARD`.
	 * @link	https://www.trantor.org/en/documentation/flow
	 */
	public function preplugin() {
	}
	/**
	 * Method call only when the plugin is executed as post-plugin.
	 * The return could be an constant (e.g. `return self::EXEC_QUIT;`) or null (`return (null);`) or nothing (`return;`).
	 * Null and zero return values are the same than returning `self::EXEC_FORWARD`.
	 * @link	https://www.trantor.org/en/documentation/flow
	 */
	public function postplugin() {
	}
	/**
	 * Method called when the plugin is executed (as pre-plugin or post-plugin),
	 * and the corresponding method (preplugin() or postplugin()) is not defined.
	 * The return could be an constant (e.g. `return self::EXEC_QUIT;`) or null (`return (null);`) or nothing (`return;`).
	 * Null and zero return values are the same than returning `self::EXEC_FORWARD`.
	 * @link	https://www.trantor.org/en/documentation/flow
	 */
	public function plugin() {
	}
}

