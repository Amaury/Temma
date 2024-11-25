<?php

/**
 * Php view
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2010-2024, Amaury Bouchard
 */

namespace Trantor\Views;

use \Trantor\Base\Log as TrLog;
use \Trantor\Exceptions\IO as TrIOException;

/**
 * View for templates written in plain PHP.
 */
class Php extends \Trantor\Web\View {
	/** Tell if the generated page could be stored in cache. */
	private bool $_isCacheable = false;
	/** Name of the template. */
	private ?string $_template = null;

	/**
	 * Tell that this view uses templates.
	 * @return	bool	True.
	*/
	public function useTemplates() : bool {
		return (true);
	}
	/**
	 * Define the used templates file.
	 * @param	string	$path		Templates include path.
	 * @param	string	$template	Name of the template.
	 * @throws	\Trantor\Exceptions\IO	If the template file doesn't exists.
	 */
	public function setTemplate(string $path, string $template) : void {
		// add the templates include path to the PHP include paths
		set_include_path($path . PATH_SEPARATOR . get_include_path());
		// check if the file exists
		TrLog::log('Trantor/Web', 'DEBUG', "Searching template '$template'.");
		if (is_file("$path/$template")) {
			$this->_template = $template;
			return;
		}
		TrLog::log('Trantor/Web', 'WARN', "No one template found with name '$template'.");
		throw new TrIOException("Can't find template '$template'.", TrIOException::NOT_FOUND);
	}
	/** Init. */
	public function init() : void {
		foreach ($this->_response->getData() as $key => $value) {
			if (isset($key[0]) && $key[0] != '_')
				$GLOBALS[$key] = $value;
			else if ($key == '_trantorCacheable' && $value === true)
				$this->_isCacheable = true;
		}
	}
	/** Write the body. */
	public function sendBody() : void {
		// processing
		ini_set('implicit_flush', false);
		ob_start();
		include($this->_template);
		$out = ob_get_contents();
		ob_end_clean();
		ini_set('implicit_flush', true);
		// cache management
		if ($this->_isCacheable && !empty($out) && ($dataSource = $this->_config->xtra('trantor-cache', 'source')) &&
		    isset($this->_dataSources[$dataSource]) && ($cache = $this->_dataSources[$dataSource])) {
			$cacheVarName = $_SERVER['HTTP_HOST'] . ':' . $_SERVER['REQUEST_URI'];
			$cache->setPrefix('trantor-cache')->set($cacheVarName, $out)->setPrefix();
		}
		// write the page
		print($out);
	}
}

