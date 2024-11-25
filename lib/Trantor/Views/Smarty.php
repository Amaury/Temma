<?php

/**
 * Smarty view
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	2007-2024, Amaury Bouchard
 */

namespace Trantor\Views;

use \Trantor\Base\Log as TrLog;
use \Trantor\Exceptions\Framework as TrFrameworkException;
use \Trantor\Exceptions\IO as TrIOException;

if (!class_exists('\Smarty\Smarty')) {
	include_once('smarty4/Autoloader.php');
	include_once('smarty4/bootstrap.php');
	require_once('smarty4/Smarty.class.php');
} else if (!function_exists('smarty_ucfirst_ascii')) {
	require_once('Smarty/functions.php');
}

/**
 * View used for Smarty templates.
 *
 * @link	http://smarty.net/
 */
class Smarty extends \Trantor\Web\View {
	/** Name of the temporary directory where Smarty compiled files must be written. */
	const COMPILED_DIR = 'templates_compile';
	/** Name of the temporary directory where Smarty cache files must be written. */
	const CACHE_DIR = 'templates_cache';
	/** Path to the smarty plugins directory. */
	const PLUGINS_DIR = 'lib/smarty-plugins';
	/** Default setting for HTML auto-escaping. */
	const DEFAULT_AUTO_ESCAPE = true;
	/** Flag telling if the page could be stored in cache. */
	private bool $_isCacheable = false;
	/** Smarty object. */
	private \Smarty|\Smarty\Smarty $_smarty;
	/** Name of the template. */
	private ?string $_template = null;

	/**
	 * Constructor.
	 * @param	array|\ArrayAccess	$dataSources	Liste de connexions à des sources de données.
	 * @param	\Trantor\Web\Config	$config		Objet de configuration.
	 * @param	\Trantor\Web\Response	$response	Objet de réponse.
	 * @throws	\Trantor\Exceptions\Framework	If something went wrong.
	 */
	public function __construct(array|\ArrayAccess $dataSources, \Trantor\Web\Config $config, ?\Trantor\Web\Response $response) {
		global $smarty;

		parent::__construct($dataSources, $config, $response);
		// check temporary directories
		$compiledDir = $config->tmpPath . '/' . self::COMPILED_DIR;
		if (!is_dir($compiledDir) && !mkdir($compiledDir, 0755))
			throw new TrFrameworkException("Unable to create directory '$compiledDir'.", TrFrameworkException::CONFIG);
		$cacheDir = $config->tmpPath . '/' . self::CACHE_DIR;
		if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755))
			throw new TrFrameworkException("Unable to create directory '$cacheDir'.", TrFrameworkException::CONFIG);
		// create the Smarty object
		if (!class_exists('\Smarty\Smarty')) {
			// smarty 4
			$this->_smarty = new \Smarty();
		} else {
			// smarty 5
			$this->_smarty = new \Smarty\Smarty();
		}
		$smarty = $this->_smarty;
		$this->_smarty->setCompileDir($compiledDir);
		$this->_smarty->setCacheDir($cacheDir);
		$this->_smarty->setErrorReporting(E_ALL & ~E_NOTICE);
		$this->_smarty->muteUndefinedOrNullWarnings();
		// auto-escaping
		$autoEscape = $config->xtra('smarty-view', 'autoEscape', self::DEFAULT_AUTO_ESCAPE);
		$this->_smarty->setEscapeHtml($autoEscape);
		// registration of plugins
		$pluginPathList = [];
		$pluginPathList[] = $config->appPath . '/' . self::PLUGINS_DIR;
		$pluginsDir = $config->xtra('smarty-view', 'pluginsDir');
		if (is_string($pluginsDir))
			$pluginPathList[] = $pluginsDir;
		else if (is_array($pluginsDir))
			$pluginPathList = array_merge($pluginPathList, $pluginsDir);
		if (!class_exists('\Smarty\Smarty')) {
			// smarty 4
			$pluginPathList = array_merge($this->_smarty->getPluginsDir(), $pluginPathList);
			$pluginPathList = array_unique($pluginPathList);
			$this->_smarty->addPluginsDir($pluginPathList);
		} else {
			// smarty 5
			$pluginPathList = array_unique($pluginPathList);
			foreach ($pluginPathList as $path) {
				$path = rtrim($path, '/');
				foreach(['function', 'modifier', 'block', 'compiler', 'prefilter', 'postfilter', 'outputfilter'] as $type) {
					foreach (glob("$path/$type.?*.php") as $filename) {
						if (preg_match('/.*\.([a-z_A-Z0-9]+)\.php$/', $filename, $matches)) {
							$pluginName = $matches[1];
							require_once($filename);
							$functionOrClassName = 'smarty_' . $type . '_' . $pluginName;
							if (function_exists($functionOrClassName) || class_exists($functionOrClassName)) {
								$this->_smarty->registerPlugin($type, $pluginName, $functionOrClassName, true, []);
							}
						}
					}
				}
			}
			// registration of native PHP modifiers
			$functions = ['array_key_exists', 'ceil', 'date', 'explode', 'floatval', 'htmlentities',
				      'md5', 'ip2long', 'is_array', 'is_numeric', 'intval', 'str_starts_with',
				      'str_ends_with', 'str_contains', 'stripslashes', 'strstr', 'strtotime', 'trim'];
			foreach ($functions as $f)
				$smarty->registerPlugin('modifier', $f, $f);
		}
	}
	/**
	 * Tell that this view use template files.
	 * @return	bool	True.
	 */
	public function useTemplates() : bool {
		return (true);
	}
	/**
	 * Define template file.
	 * @param	string	$path		Templates include path.
	 * @param	string	$template	Name of the template.
	 * @throws	\Trantor\Exceptions\IO	If the template file doesn't exist.
	 */
	public function setTemplate(string $path, string $template) : void {
		TrLog::log('Trantor/Web', 'DEBUG', "Searching template '$template'.");
		$this->_smarty->setTemplateDir($path);
		if ($this->_smarty->templateExists($template)) {
			$this->_template = $template;
			return;
		}
		TrLog::log('Trantor/Web', 'WARN', "No one template found with name '$template'.");
		throw new TrIOException("Can't find template '$template'.", TrIOException::NOT_FOUND);
	}
	/** Init. */
	public function init() : void {
		foreach ($this->_response->getData() as $key => $value) {
			if (str_starts_with($key, '__') || !str_starts_with($key, '_'))
				$this->_smarty->assign($key, $value);
			else if ($key == '_trantorCacheable' && $value === true)
				$this->_isCacheable = true;
		}
	}
	/** Write body. */
	public function sendBody() : void {
		print($this->_response->getPrependStream());
		// cache management
		if ($this->_isCacheable && ($dataSource = $this->_config->xtra('trantor-cache', 'source')) &&
		    isset($this->_dataSources[$dataSource]) && ($cache = $this->_dataSources[$dataSource])) {
			// Smarty template rendering
			$data = $this->_smarty->fetch($this->_template);
			if (!empty($data)) {
				// store the page in cache
				$cacheVarName = $_SERVER['HTTP_HOST'] . ':' . $_SERVER['REQUEST_URI'];
				$cache->setPrefix('trantor-cache')->set($cacheVarName, $data)->setPrefix();
			}
			// write the page to stdout
			print($data);
		} else {
			// direct rendering to stdout
			$this->_smarty->display($this->_template);
		}
		print($this->_response->getAppendStream());
	}
}

