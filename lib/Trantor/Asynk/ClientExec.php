<?php

/**
 * ClientExec
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2023-2024, Amaury Bouchard
 */

namespace Trantor\Asynk;

use \Trantor\Base\Log as TrLog;

/**
 * Client object used to manage asynchronous calls.
 * This object must be called only by the \Trantor\Asynk\Client object.
 */
class ClientExec {
	/** Loader. */
	private \Trantor\Base\Loader $_loader;
	/** Nom de l'objet à exécuter. */
	private string $_className;

	/**
	 * Constructor.
	 * @param	\Trantor\Base\Loader	$loader		Loader.
	 * @param	string			$className	Name of the object to execute.
	 */
	public function __construct(\Trantor\Base\Loader $loader, string $className) {
		$this->_loader = $loader;
		$this->_className = $className;
	}
	/**
	 * Intercepts the requested method.
	 * @param	string	$methodName	Method name.
	 * @param	array	$params		Parameters passed to the method.
	 */
	public function __call(string $methodName, array $params) {
		// create the task
		$taskId = $this->_loader['\Trantor\Asynk\AsynkDao']->createTask($this->_className, $methodName, $params);
	}
}

