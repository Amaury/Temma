<?php

/**
 * Method
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2023-2024, Amaury Bouchard
 * @link	https://www.trantor.org/en/documentation/helper-attr_method
 */

namespace Trantor\Attributes;

use \Trantor\Base\Log as TrLog;
use \Trantor\Exceptions\Application as TrApplicationException;

/**
 * Attribute used to define the method(s) accepted by an action or by all actions of a controller.
 *
 * Examples:
 * - Accept GET method only, for all actions of a controller:
 * use \Trantor\Attributes\Method as TrMethod;
 * #[TrMethod('GET')]
 * class SomeController extends \Trantor\Web\Controller {
 *     ...
 * }
 *
 * - Accept POST and PUT methods only:
 * #[TrMethod(['POST', 'PUT'])]
 *
 * - Accept all méthods but GET and DELETE:
 * #[TrMethod(forbidden: ['GET', 'DELETE'])]
 *
 * - Accept POST method only, redirects invalid requests
 * #[TrMethod('POST', redirect: '/')]
 *
 * @see	\Trantor\Web\Controller
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Method extends \Trantor\Web\Attribute {
	/**
	 * Constructor.
	 * @param	null|string|array	$allowed	(optional) Allowed method(s).
	 * @param	null|string|array	$forbidden	(optional) Forbidden method(s).
	 * @param	?string			$redirect	(optional) Redirection URL if a forbidden (or not authorized) method is used.
	 * @param	?string			$redirectVar	(optional) Name of the template variable which contains the redirection URL.
	 * @throws	\Trantor\Exceptions\Application	If a forbidden (or not authorized) method is used.
	 */
	public function __construct(null|string|array $allowed=null, null|string|array $forbidden=null,
	                            ?string $redirect=null, ?string $redirectVar=null) {
		try {
			if ($forbidden) {
				if (is_string($forbidden))
					$forbidden = [$forbidden];
				foreach ($forbidden as $method) {
					if (strtoupper($method) === $_SERVER['REQUEST_METHOD']) {
						TrLog::log('Trantor/Web', 'WARN', "Unauthorized method '{$_SERVER['REQUEST_METHOD']}'.");
						throw new TrApplicationException("Unauthorized method '{$_SERVER['REQUEST_METHOD']}'.", TrApplicationException::UNAUTHORIZED);
					}
				}
			}
			if (!$allowed)
				return;
			if (is_string($allowed))
				$allowed = [$allowed];
			foreach ($allowed as $method) {
				if (strtoupper($method) === $_SERVER['REQUEST_METHOD'])
					return;
			}
			TrLog::log('Trantor/Web', 'WARN', "Invalid méthod '{$_SERVER['REQUEST_METHOD']}'.");
			throw new TrApplicationException("Invalid method '{$_SERVER['REQUEST_METHOD']}'.", TrApplicationException::UNAUTHORIZED);
		} catch (TrApplicationException $e) {
			// manage redirection URL
			$url = $redirect ?:                                               // direct URL
			       $this[$redirectVar] ?:                                     // template variable
			       $this->_getConfig()->xtra('security', 'methodRedirect') ?: // specific configuration
			       $this->_getConfig()->xtra('security', 'redirect');         // general configuration
			if ($url) {
				TrLog::log('Trantor/Web', 'DEBUG', "Redirecting to '$url'.");
				$this->_redirect($url);
				throw new \Trantor\Exceptions\FlowHalt();
			}
			// no redirection: throw the exception
			throw $e;
		}
	}
}

