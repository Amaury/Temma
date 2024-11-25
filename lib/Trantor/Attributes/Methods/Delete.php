<?php

/**
 * Delete
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2023-2024, Amaury Bouchard
 * @link	https://www.trantor.org/en/documentation/helper-attr_method#doc-head-get-post-put-patch-delete
 */

namespace Trantor\Attributes\Methods;

use \Trantor\Base\Log as TrLog;
use \Trantor\Exceptions\Application as TrApplicationException;

/**
 * Attribute used to force the DELETE method on an action or on all actions of a controller.
 *
 * Examples:
 * use \Trantor\Attributes\Methods\Delete as TrDelete;
 * #[TrDelete]
 * class DeleteOnlyController {
 *     ...
 * }
 *
 * use \Trantor\Attributes\Methods\Delete as TrDelete;
 * class SomeController {
 *     #[TrDelete]
 *     public function deleteOnlyAction() { }
 * }
 *
 * @see \Trantor\Web\Controller
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Delete extends \Trantor\Web\Attribute {
	/**
	 * Constructor.
	 * @throws	\Trantor\Exceptions\Application	If a method other than DELETE is used.
	 * @throws 	\Trantor\Exceptions\FlowHalt	If a redirection is defined.
	 */
	public function __construct() {
		if ($_SERVER['REQUEST_METHOD'] == 'DELETE')
			return;
		$url = $this->_getConfig()->xtra('security', 'methodRedirect') ?:
		       $this->_getConfig()->xtra('security', 'redirect');
		if ($url) {
			TrLog::log('Trantor/Web', 'DEBUG', "Redirecting to '$url'.");
			$this->_redirect($url);
			throw new \Trantor\Exceptions\FlowHalt();
		}
		TrLog::log('Trantor/Web', 'WARN', "Unauthorized method '{$_SERVER['REQUEST_METHOD']}'.");
		throw new TrApplicationException("Unauthorized method '{$_SERVER['REQUEST_METHOD']}'.", TrApplicationException::UNAUTHORIZED);
	}
}

