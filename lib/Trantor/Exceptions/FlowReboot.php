<?php

/**
 * FlowReboot
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2020-2024, Amaury Bouchard
 */

namespace Trantor\Exceptions;

/**
 * Exception used to control the execution flow of the framework.
 */
class FlowReboot extends Flow {
	/** Constructor. */
	public function __construct() {
		parent::__construct('', \Trantor\Web\Controller::EXEC_REBOOT);
	}
}

