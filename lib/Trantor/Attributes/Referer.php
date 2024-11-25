<?php

/**
 * Referer
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2023-2024, Amaury Bouchard
 * @link	https://www.trantor.org/en/documentation/helper-attr_referer
 */

namespace Trantor\Attributes;

use \Trantor\Base\Log as TrLog;
use \Trantor\Exceptions\Application as TrApplicationException;

/**
 * Attribute used to define access authorizations on a controller or an action, depending on the REFERER header.
 *
 * Examples:
 * - Authorize requests coming from the same site only, for all actions of the controller:
 * use \Trantor\Attributes\Referer as TrReferer;
 * #[TrReferer]
 * class SomeController extends \Trantor\Web\Controller {
 *     // ...
 * }
 *
 * - Authorize requests with a referer only:
 * #[TrReferer]
 * - Authorize requests coming from the same domain:
 * #[TrReferer(true)]
 * - Authorize requests coming from the 'fubar.com' domain:
 * #[TrReferer('fubar.com')]
 * - Authorize requests coming from the 'fubar.com' and 'www.fubar.com' domains:
 * #[TrReferer(['fubar.com', 'www.fubar.com'])]
 * - Authorize requests coming from any domain ending with '.fubar.com':
 * #[TrReferer(domainSuffix: '.fubar.com')]
 * - Authorize requests coming from any domain ending with '.fubar.com' or '.foobar.com':
 * #[TrReferer(domainSuffix: ['.fubar.com', '.foobar.com'])]
 * - Authorize requests coming from any domain matching the specified regular expression:
 * #[TrReferer(domainRegex: '/^test\d?.fubar.(com|net)$/')]
 * - Authorize requests coming from the domain stored in the 'okDomain' template variable:
 * #[TrReferer(domainVar: 'okDomain')]
 * - Authorize requests coming from the domain defined in the 'refererDomain' key of
 *   the 'x-security' extended configuration:
 * #[TrReferer(domainConfig: true)]
 *
 * - Authorize requests coming from an http or https website:
 * #[TrReferer(https: null)]
 * - Authorize requests coming from an http website only:
 * #[TrReferer(https: false)]
 * - Authorize requests coming from an https website only:
 * #[TrReferer(https: true)]
 * - Authorize requests coming from a website with the same http status than the local website:
 * #[TrReferer(https: 'same')]
 *
 * - Authorize requests coming from a '/fu/bar.html' page:
 * #[TrReferer(path: '/fu/bar/html')]
 * - Authorize requests coming from a '/fu.html' or '/bar.html' page:
 * #[TrReferer(path: ['/fu.html', '/bar.html'])]
 * - Authorize requests coming from any page which path starts with '/fu/':
 * #[TrReferer(pathPrefix: '/fu/')]
 * - Authorize requests coming from any page which path starts with '/fu/' or '/bar/':
 * #[TrReferer(pathPrefix: ['/fu/', '/bar/'])]
 * - Autorize requests coming from any page which path ends with '/api.xml':
 * #[TrReferer(pathSuffix: '/api.xml')]
 * - Authorize requests coming from any page which path ends with '/api.xml' or '/api.json':
 * #[TrReferer(pathSuffix: ['/api.xml', '/api.json'])]
 * - Authorize requests coming from any page which path matches the given regular expression:
 * #[TrReferer(pathRegex: '/^\/.*testApi.*\.xml$/')]
 * - Authorize requests coming from a page which path is stored in the 'okPath' template variable:
 * #[TrReferer(pathVar: 'okPath')]
 * - Authorize requests coming from a page which path is stored in the 'refererPath' key of
 *   the 'x-security' extended configuration:
 * #[TrReferer(pathConfig: true)]
 *
 * - Authorize requests coming from 'https://www.fubar.com/some/page.html':
 * #[TrReferer(url: 'https://www.fubar.com/some/page.html')]
 * - Authorize requests coming from 'https://fu.com/bar' or 'https://bar.com/fu':
 * #[TrReferer(url: ['https://fu.com/bar', 'https://bar.com/fu'])]
 * - Authorize requests coming from an URL matching the given regular expression:
 * #[TrRequest(urlRegex: '/^.*$/')]
 * - Authorize requests coming from the URL stored in the 'okURL' template variable:
 * #[TrReferer(urlVar: 'okURL')]
 * - Authorize requests coming from the URL stored in the 'refererUrl' key of the
 *   'x-security' extended configuration:
 * #[TrReferer(urlConfig: true)]
 *
 * - Redirect if there is no referer:
 * #[TrReferer(redirect: '/login')]
 * - Redirect using the URL defined in the 'redirRef' template variable:
 * #[TrReferer(redirectVar: 'redirRef')]
 *
 * @see	\Trantor\Web\Controller
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Referer extends \Trantor\Web\Attribute {
	/**
	 * Constructor.
	 * @param	null|bool|string|array	$domain		(optional) Authorized domain or list of authorized domains.
	 * @param	null|string|array	$domainSuffix	(optional) Authorized domain suffix or list of suffixes.
	 * @param	?string			$domainRegex	(optional) Authorized domain regular expression.
	 * @param	?string			$domainVar	(optional) Name of the template variable which contains the authorized domain.
	 * @param	bool			$domainConfig	(optional) True to use the 'refererDomain' key of the 'x-security' extended configuration.
	 * @param	null|bool|string	$https		(optional) SSL configuration.
	 * @param	null|string|array	$path		(optional) Authorized path.
	 * @param	null|string|array	$pathPrefix	(optional) Authorized path prefix or list of prefixes.
	 * @param	null|string|array	$pathSuffix	(optional) Authorized path suffix or list of suffixes.
	 * @param	?string			$pathRegex	(optional) Authorized path regular expression.
	 * @param	?string			$pathVar	(optional) Name of the template variable which contains the authorized path.
	 * @param	bool			$pathConfig	(optional) True to use the 'refererPath' key of the 'x-security' extended configuration.
	 * @param	null|bool|string|array	$url		(optional) Authorized URL.
	 * @param	?string			$urlRegex	(optional) Authorized URL regular expression.
	 * @param	?string			$urlVar		(optional) Name of the template variable which contains the authorized URL.
	 * @param	bool			$urlConfig	(optional) True to use the 'refererUrl' key of the 'x-security' extended configuration.
	 * @param	?string			$redirect	(optional) Redirection URL used if the referer is not authorized.
	 * @param	?string			$redirectVar	(optional) Name of the template variable which contains the redirection URL.
	 * @throws	\Trantor\Exceptions\Application	If the referer is not authorized.
	 * @throws	\Trantor\Exceptions\FlowHalt	If the user is not authorized and a redirect URL has been given.
	 */
	public function __construct(null|bool|string|array $domain=null, null|string|array $domainSuffix=null,
	                            ?string $domainRegex=null, ?string $domainVar=null, bool $domainConfig=false,
	                            null|bool|string $https=null, null|string|array $path=null,
	                            null|string|array $pathPrefix=null, null|string|array $pathSuffix=null,
	                            ?string $pathRegex=null, ?string$pathVar=null, bool $pathConfig=false,
	                            null|bool|string|array $url=null, ?string $urlRegex=null,
	                            ?string $urlVar=null, bool $urlConfig=false,
	                            ?string $redirect=null, ?string $redirectVar=null) {
		try {
			// check referer
			if (!($_SERVER['HTTP_REFERER'] ?? false) ||
			    !($ref = parse_url($_SERVER['HTTP_REFERER']))) {
				TrLog::log('Trantor/Web', 'WARN', "No HTTP referer.");
				throw new TrApplicationException("No HTTP referer.", TrApplicationException::UNAUTHORIZED);
			}
			// check HTTPS
			if ($https === true && $ref['scheme'] != 'https') {
				TrLog::log('Trantor/Web', 'WARN', "Not HTTPS scheme.");
				throw new TrApplicationException("Hot HTTPS scheme.", TrApplicationException::UNAUTHORIZED);
			}
			if ($https === false && $ref['scheme'] != 'http') {
				TrLog::log('Trantor/Web', 'WARN', "Not HTTP scheme.");
				throw new TrApplicationException("Hot HTTP scheme.", TrApplicationException::UNAUTHORIZED);
			}
			if ($https === 'same') {
				$secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? true : false;
				if (($ref['scheme'] == 'https' && !$secure) ||
				    ($ref['scheme'] == 'http' && $secure)) {
					TrLog::log('Trantor/Web', 'WARN', "Referer and local schemes (HTTP/HTTPS) are not the same.");
					throw new TrApplicationException("Referer and local schemes (HTTP/HTTPS) are not the same.", TrApplicationException::UNAUTHORIZED);
				}
			}
			// check domain
			$checkDomains = [];
			if ($domain === true)
				$checkDomains[] = $_SERVER['SERVER_NAME'];
			if (is_string($domain))
				$checkDomains[] = $domain;
			else if (is_array($domain))
				$checkDomains = $domain;
			if ($domainVar) {
				if (is_string($this[$domainVar]))
					$checkDomains[] = $this[$domainVar];
				else if (is_array($this[$domainVar]))
					$checkDomains = array_merge($checkDomains, $this[$domainVar]);
			}
			if ($domainConfig) {
				$conf = $this->_getConfig()->xtra('security', 'refererDomain');
			if (is_string($conf))
					$checkDomains[] = $conf;
				else if (is_array($conf))
					$checkDomains = array_merge($checkDomains, $conf);
			}
			if ($checkDomains) {
				$found = false;
				foreach ($checkDomains as $domain) {
					if ($ref['host'] == $domain) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					TrLog::log('Trantor/Web', 'WARN', "No matching domain.");
					throw new TrApplicationException("No matching domain.", TrApplicationException::UNAUTHORIZED);
				}
			}
			// check domain suffix
			if ($domainSuffix) {
				$checkDomains = is_array($domainSuffix) ? $domainSuffix : [$domainSuffix];
				$found = false;
				foreach ($checkDomains as $domain) {
					if (str_ends_with($ref['host'], $domain)) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					TrLog::log('Trantor/Web', 'WARN', "No matching domain suffix.");
					throw new TrApplicationException("No matching domain suffix.", TrApplicationException::UNAUTHORIZED);
				}
			}
			// check domain regex
			if ($domainRegex && preg_match($domainRegex, $ref['host']) === false) {
				TrLog::log('Trantor/Web', 'WARN', "Domain doesn't match regex.");
				throw new TrApplicationException("Domain doesn't match regex.", TrApplicationException::UNAUTHORIZED);
			}
			// check URL
			$checkUrl = [];
			if (is_string($url))
				$checkUrl[] = $url;
			else if (is_array($url))
				$checkUrl = $url;
			if ($urlVar) {
				if (is_string($this[$urlVar]))
					$checkUrl[] = $this[$urlVar];
				else if (is_array($this[$urlVar]))
					$checkUrl = array_merge($checkUrl, $this[$urlVar]);
			}
			if ($urlConfig) {
				$conf = $this->_getConfig()->xtra('security', 'refererUrl');
				if (is_string($conf))
					$checkUrl[] = $conf;
				else if (is_array($conf))
					$checkUrl = array_merge($checkUrl, $conf);
			}
			if ($checkUrl) {
				$found = false;
				foreach ($checkUrl as $url) {
					if ($_SERVER['HTTP_REFERER'] == $url) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					TrLog::log('Trantor/Web', 'WARN', "No matching URL.");
					throw new TrApplicationException("No matching URL.", TrApplicationException::UNAUTHORIZED);
				}
			}
			// check URL regex
			if ($urlRegex && preg_match($urlRegex, $_SERVER['HTTP_REFERER']) === false) {
				TrLog::log('Trantor/Web', 'WARN', "Referer URL doesn't match regex.");
				throw new TrApplicationException("Referer URL doesn't match regex.", TrApplicationException::UNAUTHORIZED);
			}
			// check path
			$checkPath = [];
			if (is_string($path))
				$checkPath[] = $path;
			else if (is_array($path))
				$checkPath = $path;
			if ($pathVar) {
				if (is_string($this[$pathVar]))
					$checkPath[] = $this[$pathVar];
				else if (is_array($this[$pathVar]))
					$checkPath = array_merge($checkPath, $this[$pathVar]);
			}
			if ($pathConfig) {
				$conf = $this->_getConfig()->xtra('security', 'refererPath');
				if (is_string($conf))
					$checkPath[] = $conf;
				else if (is_array($conf))
					$checkPath = array_merge($checkPath, $conf);
			}
			if ($checkPath) {
				$found = false;
				foreach ($checkPath as $path) {
					if ($ref['path'] == $path) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					TrLog::log('Trantor/Web', 'WARN', "No matching Path.");
					throw new TrApplicationException("No matching Path.", TrApplicationException::UNAUTHORIZED);
				}
			}
			// check path prefix
			if ($pathPrefix) {
				$checkPath = is_array($pathPrefix) ? $pathPrefix : [$pathPrefix];
				$found = false;
				foreach ($checkPath as $path) {
					if (str_starts_with($ref['path'], $path)) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					TrLog::log('Trantor/Web', 'WARN', "No matching path prefix.");
					throw new TrApplicationException("No matching path prefix.", TrApplicationException::UNAUTHORIZED);
				}
			}
			// check path suffix
			if ($pathSuffix) {
				$checkPath = is_array($pathSuffix) ? $pathSuffix : [$pathSuffix];
				$found = false;
				foreach ($checkPath as $path) {
					if (str_ends_with($ref['path'], $path)) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					TrLog::log('Trantor/Web', 'WARN', "No matching path suffix.");
					throw new TrApplicationException("No matching path suffix.", TrApplicationException::UNAUTHORIZED);
				}
			}
			// check path regex
			if ($pathRegex && preg_match($pathRegex, $ref['path']) === false) {
				TrLog::log('Trantor/Web', 'WARN', "Path doesn't match regex.");
				throw new TrApplicationException("Path doesn't match regex.", TrApplicationException::UNAUTHORIZED);
			}
		} catch (TrApplicationException $e) {
			// manage redirection URL
			$url = $redirect ?:                                                // direct URL
			       $this[$redirectVar] ?:                                      // template variable
			       $this->_getConfig()->xtra('security', 'refererRedirect') ?: // specific configuration
			       $this->_getConfig()->xtra('security', 'redirect');          // general configuration
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

