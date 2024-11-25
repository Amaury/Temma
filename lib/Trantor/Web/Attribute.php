<?php

/**
 * Attribute
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2023-2024, Amaury Bouchard
 */

namespace Trantor\Web;

use \Trantor\Base\Log as TrLog;
use \Trantor\Exceptions\Application as TrApplicationException;

/**
 * Base object for Trantor attributes, used to affect the behaviour of the framework when an action is accessed.
 *
 * Can't be instanciated directly. Real attributes must inherit from this class.
 *
 * @see	\Trantor\Web\Controller
 */
abstract class Attribute implements \ArrayAccess {
	/* ********** METHODS CALLABLE BY THE CHILDREN OBJECTS ********** */
	/**
	 * Magical method which returns the requested data source.
	 * @param	string	$dataSource	Name of the data source.
	 * @return	\Trantor\Base\Datasource	Data source object, or null if the source is not set.
	 */
	final public function __get(string $dataSource) : ?\Trantor\Base\Datasource {
		global $trantor;
		return ($trantor->getLoader()->dataSources[$dataSource] ?? null);
	}
	/**
	 * Magical method used to know if a data source exists.
	 * @param	string	$dataSource	Name of the data source.
	 * @return	bool	True if the data source exists.
	 */
	final public function __isset(string $dataSource) : bool {
		global $trantor;
		return (isset($trantor->getLoader()->dataSources[$dataSource]));
	}
	/**
	 * Returns the loader object.
	 * @return	\Trantor\Base\Loader	The loader object.
	 */
	final protected function _getLoader() : \Trantor\Base\Loader {
		global $trantor;
		return ($trantor->getLoader());
	}
	/**
	 * Returns the session object.
	 * @return	\Trantor\Base\Session	The session object.
	 */
	final protected function _getSession() : \Trantor\Base\Session {
		global $trantor;
		return ($trantor->getLoader()->session);
	}
	/**
	 * Returns the configuration object.
	 * @return	\Trantor\Web\Config	The configuration object.
	 */
	final protected function _getConfig() : \Trantor\Web\Config {
		global $trantor;
		return ($trantor->getLoader()->config);
	}
	/**
	 * Returns the request object.
	 * @return	\Trantor\Web\Request	The request object.
	 */
	final protected function _getRequest() : \Trantor\Web\Request {
		global $trantor;
		return ($trantor->getLoader()->request);
	}
	/**
	 * Returns the response object.
	 * @return	\Trantor\Web\Response	The response object.
	 */
	final protected function _getResponse() : \Trantor\Web\Response {
		global $trantor;
		return ($trantor->getLoader()->response);
	}
	/**
	 * Method used to raise en HTTP error (403, 404, 500, ...).
	 * @param	int	$code	The HTTP error code.
	 */
	final protected function _httpError(int $code) : void {
		global $trantor;
		$trantor->getLoader()->response->setHttpError($code);
	}
	/**
	 * Method used to tell the HTTP return code (like the httpError() method,
	 * but without raising an error).
	 * @param	int	$code	The HTTP return code.
	 */
	final protected function _httpCode(int $code) :void {
		global $trantor;
		$trantor->getLoader()->response->setHttpCode($code);
	}
	/**
	 * Returns the configured HTTP error.
	 * @return	int	The configured error code (403, 404, 500, ...) or null
	 *			if no error was configured.
	 */
	final protected function _getHttpError() : ?int {
		global $trantor;
		return ($trantor->getLoader()->response->getHttpError());
	}
	/**
	 * Returns the configured HTTP return code.
	 * @return	int	The configured return code, or null if no code was configured.
	 */
	final protected function _getHttpCode() : ?int {
		global $trantor;
		return ($trantor->getLoader()->response->getHttpCode());
	}
	/**
	 * Define an HTTP redirection (302).
	 * @param	?string	$url	Redirection URL, or null to remove the redirection.
	 */
	final protected function _redirect(?string $url) : void {
		global $trantor;
		$trantor->getLoader()->response->setRedirection($url);
	}
	/**
	 * Define an HTTP redirection (301).
	 * @param	string	$url	Redirection URL.
	 */
	final protected function _redirect301(string $url) : void {
		global $trantor;
		$trantor->getLoader()->response->setRedirection($url, true);
	}
	/**
	 * Define the view to use.
	 * @param	string	$view	Name of the view.
	 * @return	\Trantor\Web\Attribute	The current object.
	 */
	final protected function _view(string $view) : \Trantor\Web\Attribute {
		global $trantor;
		$trantor->getLoader()->response->setView($view);
		return ($this);
	}
	/**
	 * Define the template to use.
	 * @param	string	$template	Template name.
	 * @return	\Trantor\Web\Attribute	The current object.
	 */
	final protected function _template(string $template) : \Trantor\Web\Attribute {
		global $trantor;
		$trantor->getLoader()->response->setTemplate($template);
		return ($this);
	}
	/**
	 * Define the prefix to the template path.
	 * @param	string	$prefix	The template prefix path.
	 * @return	\Trantor\Web\Attribute	The current object.
	 */
	final protected function _templatePrefix(string $prefix) : \Trantor\Web\Attribute {
		global $trantor;
		$trantor->getLoader()->response->setTemplatePrefix($prefix);
		return ($this);
	}

	/* ********** MANAGEMENT OF "TEMPLATE VARIABLES" ********** */
	/**
	 * Set a template variable, array-like syntax.
	 * @param       string  $name   Name of the variable.
	 * @param       mixed   $value  Associated value.
	 */
	final public function offsetSet(mixed $name, mixed $value) : void {
		global $trantor;
		$trantor->getLoader()->response[$name] = $value;
	}
	/**
	 * Return a template variable, array-like syntax.
	 * @param       string  $name   Variable name.
	 * @return      mixed   The template variable's data or null if it doesn't exist.
	 */
	public function offsetGet(mixed $name) : mixed {
		global $trantor;
		return ($trantor->getLoader()->response[$name] ?? null);
	}
	/**
	 * Remove a template variable.
	 * @param       string  $name   Name of the variable.
	 */
	public function offsetUnset(mixed $name) : void {
		global $trantor;
		unset($trantor->getLoader()->response[$name]);
	}
	/**
	 * Tell if a template variable exists.
	 * @param       string  $name   Name of the variable.
	 * @return      bool    True if the variable was defined, false otherwise.
	 */
	public function offsetExists(mixed $name) : bool {
		global $trantor;
		return (isset($trantor->getLoader()->response[$name]));
	}
}

