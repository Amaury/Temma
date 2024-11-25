<?php

/**
 * Redirect
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2023-2024, Amaury Bouchard
 * @link	https://www.trantor.org/en/documentation/helper-attr_redirect
 */

namespace Trantor\Attributes;

use \Trantor\Base\Log as TrLog;
use \Trantor\Exceptions\Application as TrApplicationException;

/**
 * Attribute used to force a redirection on an action (or an all actions of a controller).
 *
 * Examples:
 * - Redirects access to any action of a controller:
 * use \Trantor\Attributes\Redirect as TrRedirect;
 * #[TrRedirect['/somewhere/else']
 * class SomeController extends \Trantor\Web\Controller {
 *     ...
 * }
 *
 * - Redirect access to one sepcific action:
 * use \Trantor\Attributes\Redirect as TrRedirect;
 * class SomeController extends \Trantor\Web\Controller {
 *     #[TrRedirect('/somewhere/else')]
 *     public function someAction() {
 *         // never executed
 *     }
 * }
 *
 * - Redirect to the URL defined in the 'goRedir' template variable:
 * #[TrRedirect(var: 'goRedir')]
 *
 * - Redirect using the 'redirect' key in the 'x-security' extended configuration:
 * #[TrRedirect(config: true)]
 *
 * @see	\Trantor\Web\Controller
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Redirect extends \Trantor\Web\Attribute {
	/**
	 * Constructor.
	 * @param	?string	$url	(optional) Redirection URL.
	 * @param	?string	$var	(optional) Name of the template variable which contains the redirection URL.
	 * @throws	\Trantor\Exceptions\Flow		When a redirection URL has been defined.
	 * @throws	\Trantor\Exceptions\Application	If no redirection URL has been defined.
	 */
	public function __construct(?string $url=null, ?string $var=null) {
		$url = $url ?:                                            // direct URL
		       $this[$var] ?:                                     // template variable
		       $this->_getConfig()->xtra('security', 'redirect'); // configuration
		if ($url) {
			TrLog::log('Trantor/Web', 'DEBUG', "Redirecting to '$url'.");
			$this->_redirect($url);
			throw new \Trantor\Exceptions\FlowHalt();
		}
		// no redirection URL defined
		TrLog::log('Trantor/Web', 'DEBUG', "No redirection URL defined.");
		throw new \Trantor\Exceptions\Application("Redirect attribute with no defined URL.", \Trantor\Exceptions\Application::UNAUTHORIZED);
	}
}

