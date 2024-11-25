<?php

/**
 * Framework
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2007-2024, Amaury Bouchard
 */

namespace Trantor\Web;

use \Trantor\Base\Log as TrLog;
use \Trantor\Exceptions\Flow as TrFlowException;
use \Trantor\Exceptions\Http as TrHttpException;
use \Trantor\Exceptions\Framework as TrFrameworkException;
use \Trantor\Exceptions\IO as TrIOException;

/**
 * Main framework management object.
 *
 * @see		\Trantor\Web\Controller
 * @see		\Trantor\Web\View
 */
class Framework {
	/** Version number of Trantor's last tagged release. */
	const TRANTOR_VERSION = '2.8.0';
	/** Name of the root action. */
	const CONTROLLERS_ROOT_ACTION = '__invoke';
	/** Name of the proxy action. */
	const CONTROLLERS_PROXY_ACTION = '__proxy';
	/** Old name of the proxy action. */
	const CONTROLLERS_OLD_PROXY_ACTION = '__clone';
	/** Name of the default action. */
	const CONTROLLERS_DEFAULT_ACTION = '__call';
	/** Name of controllers' init method. */
	const CONTROLLERS_INIT_METHOD = '__wakeup';
	/** Name of controllers' finalize method. */
	const CONTROLLERS_FINALIZE_METHOD = '__sleep';
	/** Name of plugins' preplugin method. */
	const PLUGINS_PREPLUGIN_METHOD = 'preplugin';
	/** Name of plugins' postplugin method. */
	const PLUGINS_POSTPLUGIN_METHOD = 'postplugin';
	/** Name of plugins' default plugin method. */
	const PLUGINS_PLUGIN_METHOD = 'plugin';
	/** Default extension of template files. */
	const TEMPLATE_EXTENSION = '.tpl';
	/** Maximum recursion depth when searching for routes. */
	const ROUTE_MAX_DEPTH = 4;
	/** Name of the template variable that will contain data automatically imported from configuration. */
	const AUTOIMPORT_VARIABLE = 'conf';
	/** Name of the default data source. */
	const DEFAULT_DATASOURCE = 'db';
	/** Loader object. */
	private ?\Trantor\Base\Loader $_loader = null;
	/** Configuration object. */
	private ?\Trantor\Web\Config $_config = null;
	/** List of data sources. */
	private ?\Trantor\Utils\Registry $_dataSources = null;
	/** Session object. */
	private ?\Trantor\Base\Session $_session = null;
	/** Request object. */
	private ?\Trantor\Web\Request $_request = null;
	/** Response object. */
	private ?\Trantor\Web\Response $_response = null;
	/** "neutral" controller object. */
	private ?\Trantor\Web\Controller $_executorController = null;
	/** Name of the executed controller. */
	private ?string $_controllerName = null;
	/** Name of the object corresponding to the controller. */
	private ?string $_objectControllerName = null;
	/** Name of the executed action. */
	private ?string $_actionName = null;
	/** Reflexion object over the controller (for checking purposes). */
	private ?\ReflectionClass $_controllerReflection = null;

	/**
	 * Constructor. Framework init: read the configuration, connect to data sources, create the session.
	 * @param	?\Trantor\Base\Loader	$loader	(optional) Loader object. (defaults to null)
	 */
	public function __construct(?\Trantor\Base\Loader $loader=null) {
		// extraction of request parameters
		$this->_request = $loader['request'] ?? new \Trantor\Web\Request();
		// create the response object
		$this->_response = $loader['response'] ?? new \Trantor\Web\Response();
		// load the configuration, log system init
		if (isset($loader['config']))
			$this->_config = $loader['config'];
		else
			$this->_loadConfig();
		// create the loader object (dependency injection container)
		if ($loader) {
			$this->_loader = $loader;
			$this->_loader['config'] ??= $this->_config;
			$this->_loader['request'] ??= $this->_request;
			$this->_loader['response'] ??= $this->_response;
			$this->_loader['trantor'] ??= $this;
		} else {
			$loaderName = $this->_config->loader;
			$this->_loader = new $loaderName([
				'config'   => $this->_config,
				'request'  => $this->_request,
				'response' => $this->_response,
				'trantor'    => $this,
			]);
		}
		// configure the loader with the defined aliases and prefixes
		if (isset($this->_config->loaderAliases))
			$this->_loader->setAliases($this->_config->loaderAliases);
		if (isset($this->_config->loaderPrefixes))
			$this->_loader->setPrefixes($this->_config->loaderPrefixes);
		// initialization of the log system
		$this->_configureLog();
		// check the requested URL and log it
		if ($_SERVER['REQUEST_URI'] == '/index.php') {
			TrLog::log('Trantor/Web', 'DEBUG', "Requested URL '/index.php', redirecting to '/'.");
			header("Location: /");
			exit();
		} else if (($_SERVER['REQUEST_URI'] ?? null))
			TrLog::log('Trantor/Web', 'DEBUG', "Processing URL '" . $_SERVER['REQUEST_URI'] . "'.");
		// connect to data sources
		$this->_dataSources = new \Trantor\Utils\Registry();
		foreach ($this->_config->dataSources as $name => $dsParam) {
			$this->_dataSources[$name] = \Trantor\Base\Datasource::metaFactory($dsParam);
		}
		$this->_loader['dataSources'] = $this->_dataSources;
		// get the session if needed
		if ($this->_config->enableSessions && !isset($this->_loader['session'])) {
			$sessionSource = (isset($this->_config->sessionSource) && isset($this->_dataSources[$this->_config->sessionSource])) ?
					 $this->_dataSources[$this->_config->sessionSource] : null;
			$this->_session = \Trantor\Base\Session::factory($sessionSource, $this->_config->sessionName, $this->_config->sessionDuration, $this->_config->cookieDomain);
			$this->_loader['session'] = $this->_session;
			// manage flash variables
			$flash = $this->_session->extractPrefix('__');
			$this->_response->addData($flash);
		}
	}
	/**
	 * In case of automatic configuration (using a generated configuration object), this method store the configuration object.
	 * @param	\Trantor\Web\Config	$config	The configuration object to use.
	 * @see		Trantor/bin/configObjectGenerator.php
	 */
	public function setConfig(\Trantor\Web\Config $config)  : void {
		$this->_config = $config;
		$this->_loader->set('config', $config);
	}
	/**
	 * Starts the execution flow: extract request parameters, initialize variables, pre-plugins/controller/post-plugins execution.
	 * @param	?bool	$processView	(optional) Set to false or null to avoid processing of the view (or redirection). Defaults to true.
	 *					- false: returns an associative array of data (the template variables) or a string if a redirection was defined.
	 *					- null: returns null.
	 *					- true: the view or the redirection is processed.
	 * @param	bool	$sendHeaders	(optional) Set to false to avoid sending headers. Defaults to true.
	 * @return	null|string|array	Null by default, or a string (for a redirection) or an associative array of data if the
	 *					$returnData parameter is set to true.
	 */
	public function process(?bool $processView=true, bool $sendHeaders=true) : null|string|array {
		/* ********** INIT ********** */
		// create the executor controller if needed
		$this->_executorController = $this->_executorController ?? new \Trantor\Web\Controller($this->_loader);
		// variables init
		$this->_executorController['URL'] = $this->_request->getPathInfo();
		$this->_executorController['CONTROLLER'] = $this->_request->getController();
		$this->_executorController['ACTION'] = $this->_request->getAction();
		// import of "autoimport" variables defined in the configuration file
		$this->_executorController[self::AUTOIMPORT_VARIABLE] = $this->_config->autoimport;

		/* ********** NAME OF CONTROLLER/ACTION ********** */
		$this->_setControllerName();
		$this->_setActionName();

		/* ********** PRE-PLUGINS ********** */
		TrLog::log('Trantor/Web', 'DEBUG', "Processing of pre-process plugins.");
		$execStatus = \Trantor\Web\Controller::EXEC_FORWARD;
		// generate the list of pre-plugins
		$prePlugins = $this->_generatePluginsList('pre');
		// processing of pre-plugins
		while (($pluginName = current($prePlugins)) !== false) {
			next($prePlugins);
			if (empty($pluginName))
				continue;
			// execution of the pre-plugin
			try {
				$execStatus = $this->_execPlugin($pluginName, 'pre');
			} catch (TrFlowException $fe) {
				$execStatus = $fe->getCode();
			}
			// if asked for, stops all processing and quit immediately
			if ($execStatus === \Trantor\Web\Controller::EXEC_QUIT) {
				TrLog::log('Trantor/Web', 'DEBUG', "Premature but wanted end of processing.");
				return (($processView === false) ? $this->_response->getData() : null);
			}
			// re-compute controller/action names (is case of the plugin modified the controller, the action,
			// the default namespace, or the include paths)
			$this->_setControllerName();
			$this->_setActionName();
			// check the execution status returned by the plugin
			if ($execStatus === \Trantor\Web\Controller::EXEC_STOP ||
			    $execStatus === \Trantor\Web\Controller::EXEC_HALT) {
				// stops pre-plugins processing
				break;
			} else if ($execStatus === \Trantor\Web\Controller::EXEC_RESTART) {
				// restarts all pre-plugins execution
				$prePlugins = $this->_generatePluginsList('pre');
				reset($prePlugins);
			} else if ($execStatus === \Trantor\Web\Controller::EXEC_REBOOT) {
				// restarts the execution from the very beginning
				$this->process($processView, $sendHeaders);
				return (($processView === false) ? $this->_response->getData() : null);
			}
		}

		/* ********** CONTROLLER ********** */
		if ($this->_controllerReflection->getName() == 'Trantor\Web\Controller')
			throw new TrHttpException("The requested page doesn't exists.", 404);
		if (!$execStatus) { // $execStatus === \Trantor\Web\Controller::EXEC_FORWARD || $execStatus === \Trantor\Web\Controller::EXEC_FORWARD_THROWABLE
			do {
				// process the controller
				TrLog::log('Trantor/Web', 'DEBUG', "Controller processing.");
				try {
					$execStatus = $this->_executorController->_subProcess($this->_objectControllerName, $this->_actionName);
				} catch (TrFlowException $fe) {
					$execStatus = $fe->getCode();
				}
			} while ($execStatus === \Trantor\Web\Controller::EXEC_RESTART);
			// if asked, restarts the execution from the very beginning
			if ($execStatus === \Trantor\Web\Controller::EXEC_REBOOT) {
				$this->process($processView, $sendHeaders);
				return (($processView === false) ? $this->_response->getData() : null);
			}
			// if asked for, stops all processing and quit immediately
			if ($execStatus === \Trantor\Web\Controller::EXEC_QUIT) {
				TrLog::log('Trantor/Web', 'DEBUG', "Premature but wanted end of processing.");
				return (($processView === false) ? $this->_response->getData() : null);
			}
		}

		/* ********** POST-PLUGINS ********** */
		if (!$execStatus) { // $execStatus === \Trantor\Web\Controller::EXEC_FORWARD || $execStatus === \Trantor\Web\Controller::EXEC_FORWARD_THROWABLE
			TrLog::log('Trantor/Web', 'DEBUG', "Processing of post-process plugins.");
			// generate the list of post-plugins
			$postPlugins = $this->_generatePluginsList('post');
			// processing of post-plugins
			while (($pluginName = current($postPlugins)) !== false) {
				next($postPlugins);
				if (empty($pluginName))
					continue;
				// execution of the post-plugin
				try {
					$execStatus = $this->_execPlugin($pluginName, 'post');
				} catch (TrFlowException $fe) {
					$execStatus = $fe->getCode();
				}
				// if asked for, stops all processing and quit immediately
				if ($execStatus === \Trantor\Web\Controller::EXEC_QUIT) {
					TrLog::log('Trantor/Web', 'DEBUG', "Premature but wanted end of processing.");
					return (($processView === false) ? $this->_response->getData() : null);
				}
				// if asked, restarts the execution from the very beginning
				if ($execStatus === \Trantor\Web\Controller::EXEC_REBOOT) {
					$this->process($processView, $sendHeaders);
					return (($processView === false) ? $this->_response->getData() : null);
				}
				// if asked, stops pre-plugins processing
				if ($execStatus === \Trantor\Web\Controller::EXEC_STOP ||
				    $execStatus === \Trantor\Web\Controller::EXEC_HALT) {
					break;
				}
				// if asked, restarts all post-plugins execution
				// si demandé, reprise des traitements de tous les post-plugins
				if ($execStatus === \Trantor\Web\Controller::EXEC_RESTART) {
					$this->_setControllerName();
					$this->_setActionName();
					$postPlugins = $this->_generatePluginsList('post');
					reset($postPlugins);
				}
			}
		}

		/* ********** RESPONSE ********** */
		// management of HTTP errors
		$httpError = $this->_response->getHttpError();
		if (isset($httpError)) {
			TrLog::log('Trantor/Web', 'WARN', "HTTP error '$httpError': " . $this->_request->getController()  . "/" . $this->_request->getAction());
			throw new TrHttpException("HTTP error.", $httpError);
		}
		// management of redirection if needed
		$url = $this->_response->getRedirection();
		if (!empty($url)) {
			TrLog::log('Trantor/Web', 'DEBUG', "Redirecting to '$url'.");
			if ($processView === false)
				return ($url);
			if ($this->_response->getRedirectionCode() == 301)
				header('HTTP/1.1 301 Moved Permanently');
			header("Location: $url");
			exit();
		}
		// return data if asked
		if ($processView === false)
			return ($this->_response->getData());
		if ($processView === null)
			return (null);

		/* ********** VIEW ********** */
		// load the view object
		$view = $this->_loadView();
		if ($view === false)
			return (null);
		// init the view
		$this->_initView($view);
		// send HTTP headers
		if ($sendHeaders) {
			TrLog::log('Trantor/Web', 'DEBUG', "Writing of response headers.");
			$view->sendHeaders($this->_response->getHeaders());
		}
		// send data body
		TrLog::log('Trantor/Web', 'DEBUG', "Writing of response body.");
		$view->sendBody();
		return (null);
	}
	/**
	 * Returns the path to the HTML page corresponding to an HTTP error code.
	 * @param	int	$code	The HTTP error code.
	 * @return	?string	The path to the file, or null if it's not defined.
	 */
	public function getErrorPage(int $code)  : ?string {
		$errorPages = $this->_config->errorPages;
		if (isset($errorPages[$code]) && !empty($errorPages[$code]))
			return ($this->_config->webPath . '/' . $errorPages[$code]);
		if (isset($errorPages['default']) && !empty($errorPages['default']))
			return ($this->_config->webPath . '/' . $errorPages['default']);
		return (null);
	}

	/* ********** GETTERS ********** */
	/**
	 * Returns the loader.
	 * @return	?\Trantor\Base\Loader	The current loader object.
	 */
	public function getLoader() : ?\Trantor\Base\Loader {
		return ($this->_loader);
	}
	/**
	 * Returns the controller object name.
	 * @return	string	The object controller name.
	 */
	public function getControllerName() : string {
		return ($this->_objectControllerName);
	}
	/**
	 * Returns the action name.
	 * @return	string	The action name.
	 */
	public function getActionName() : string {
		return ($this->_actionName);
	}

	/* ********** PRIVATE METHODS ********** */
	/**
	 * Load the configuration file.
	 * @throws	\Trantor\Exceptions\Framework	If the file is not readable and well-formed.
	 */
	private function _loadConfig() : void {
		// fetch the path to the application root path
		$appPath = realpath(dirname($_SERVER['SCRIPT_FILENAME']) . '/..');
		if (empty($appPath) || !is_dir($appPath))
			throw new TrFrameworkException("Unable to find application's root path.", TrFrameworkException::CONFIG);
		// read the configuration
		$this->_config = new \Trantor\Web\Config($appPath);
		$this->_config->readConfigurationFile();
	}
	/** Configure the log system. */
	private function _configureLog() : void {
		$logPath = $this->_config->logPath;
		$logManager = $this->_config->logManager;
		// check if the log is disabled
		if (!$logPath && !$logManager) {
			TrLog::disable();
			return;
		}
		// checked if a log file was set
		if ($logPath)
			TrLog::setLogFile($logPath);
		// check is a log manager was set
		if ($logManager) {
			if (is_string($logManager))
				$logManager = [$logManager];
			foreach ($logManager as $managerName) {
				// check if the object exists and implements the right interface
				try {
					$reflect = new \ReflectionClass($managerName);
					if (!$reflect->implementsInterface('\Trantor\Web\LogManager'))
						throw new TrFrameworkException("Log manager '$managerName' doesn't implements \Trantor\Web\LogManager interface.", TrFrameworkException::CONFIG);
					if ($reflect->implementsInterface('\Trantor\Base\Loadable'))
						$manager = new $managerName($this->_loader);
					else
						$manager = new $managerName();
					TrLog::addCallback(function($traceId, $text, $priority, $class) use ($manager) {
						return $manager->log($traceId, $text, $priority, $class);
					});
				} catch (\ReflectionException $re) {
					throw new TrFrameworkException("Log manager '$managerName' doesn't exist.", TrFrameworkException::CONFIG);
				}
			}
		}
		// manage log thresholds
		$logLevels = $this->_config->logLevels;
		$usedLogLevels = TrLog::checkLogLevel($logLevels);
		if (!$usedLogLevels && is_array($logLevels)) {
			$usedLogLevels = [];
			foreach ($logLevels as $class => $level) {
				if (($level = TrLog::checkLogLevel($level)))
					$usedLogLevels[$class] = $level;
			}
		}
		if (!$usedLogLevels)
			$usedLogLevels = \Trantor\Web\Config::LOG_LEVEL;
		TrLog::setThreshold($usedLogLevels);
		// manage buffering log thresholds
		$bufferingLogLevels = $this->_config->bufferingLogLevels;
		if ($bufferingLogLevels) {
			$usedBufferingLogLevels = TrLog::checkLogLevel($bufferingLogLevels);
			if (!$usedBufferingLogLevels && is_array($bufferingLogLevels)) {
				$usedBufferingLogLevels = [];
				foreach ($bufferingLogLevels as $class => $level) {
					if (($level = TrLog::checkLogLevel($level)))
						$usedBufferingLogLevels[$class] = $level;
				}
			}
			if ($usedBufferingLogLevels)
				TrLog::setBufferingThreshold($usedBufferingLogLevels);
		}
	}

	/* ********** CONTROLLERS/PLUGINS LOADING ********** */
	/**
	 * Define the name of the loaded controller
	 * @throws	\Trantor\Exceptions\Http	If the controller doesn't exist.
	 */
	private function _setControllerName() : void {
		$this->_controllerName = $this->_request->getController();
		if (!empty($proxyName = $this->_config->proxyController)) {
			// a proxy controller was defined
			$this->_objectControllerName = $proxyName;
		} else if (empty($this->_controllerName = $this->_request->getController())) {
			// no requested controller, use the root controller
			TrLog::log('Trantor/Web', 'INFO', "No controller defined, use the root controller.");
			$this->_objectControllerName = $this->_config->rootController;
		} else {
			// a usable controller was defined on the URL
			// check if the requested controller is a virtual controller (managed by a route)
			$routeName = $this->_config->routes[$this->_controllerName] ?? null;
			if ($routeName) {
				TrLog::log('Trantor/Web', 'INFO', "Routing '" . $this->_controllerName . "' to '$routeName'.");
				$this->_objectControllerName = $routeName;
			} else {
				// check controller name
				$lastBackslashPos = strrpos($this->_controllerName, '\\');
				if ($lastBackslashPos === false) {
					// there is no namespace
					// checks that the controller name's first letter is in lower case
					$firstLetter = substr($this->_controllerName, 0, 1);
					if ($firstLetter != lcfirst($firstLetter)) {
						TrLog::log('Trantor/Web', 'ERROR', "Bad name for controller '" . $this->_controllerName . "' (must start by a lower-case character).");
						throw new TrHttpException("Bad name for controller '" . $this->_controllerName . "' (must start by a lower-case character).", 404);
					}
					// ensure the controller object's name starts with an upper-case letter
					$this->_objectControllerName = ucfirst($this->_controllerName);
				} else {
					// there is a namespace
					// ensure the controller object's name starts with an upper-case letter
					$this->_objectControllerName = substr($this->_controllerName, 0, $lastBackslashPos + 1) .
								       ucfirst(substr($this->_controllerName, $lastBackslashPos + 1));
				}
			}
			// management of the suffix
			$controllersSuffix = $this->_config->controllersSuffix;
			if (!empty($controllersSuffix) && substr($this->_objectControllerName, -strlen($controllersSuffix)) != $controllersSuffix)
				$this->_objectControllerName .= $controllersSuffix;
		}
		if (empty($this->_objectControllerName)) {
			// no requested controller, use the default controller
			TrLog::log('Trantor/Web', 'INFO', "No controller found, use the default controller.");
			$this->_objectControllerName = $this->_config->defaultController;
			if (empty($this->_objectControllerName)) {
				TrLog::log('Trantor/Wen', 'ERROR', "No defined controller.");
				throw new TrHttpException("No defifned controller.", 404);
			}
		}
		// if the controller object's name doesn't start with a backslash, prepend the default namespace
		if ($this->_objectControllerName[0] != '\\') {
			$defaultNamespace = rtrim(($this->_config->defaultNamespace ?? ''), '\\');
			$this->_objectControllerName = "$defaultNamespace\\" . $this->_objectControllerName;
		}
		// check that the controller object exists
		try {
			$this->_controllerReflection = new \ReflectionClass($this->_objectControllerName);
		} catch (\ReflectionException $e) {
			// the requested controller object doesn't exist, use the default controller
			//TrLog::log('Trantor/Web', 'ERROR', "No controller object '" . $this->_objectControllerName . "'.");
			//throw new \Trantor\Exceptions\Http("No controller object '" . $this->_objectControllerName . "'.", 404);
			$this->_objectControllerName = $this->_config->defaultController;
			$this->_controllerReflection = new \ReflectionClass($this->_objectControllerName);
		}
		// check how the controller name is spelled
		if ($this->_controllerReflection->getName() !== trim($this->_objectControllerName, '\ '))
			throw new TrHttpException("Bad name for controller '" . $this->_controllerName . "'.", 404);
	}
	/**
	 * Generate the list of pre- or post-plugins.
	 * @param	string	$type	'pre' or 'post'.
	 * @return	array	List of plugin names.
	 */
	private function _generatePluginsList(string $type) : array {
		$plugins = $this->_config->plugins ?? [];
		$result = [];
		// loop on plugin configuration entries
		foreach ($plugins as $pluginKey => $pluginData) {
			if (!is_string($pluginKey))
				continue;
			$pluginData = is_array($pluginData) ? $pluginData : [$pluginData];
			// global list of preplugins
			if ($pluginKey == "_$type") {
				$result = array_merge($result, $pluginData);
				continue;
			}
			// controller-specific configuration, or inverse controller-specific configuration
			$inverse = false;
			if (str_starts_with($pluginKey, '-')) {
				$inverse = true;
				$pluginKey = mb_substr($pluginKey, 1);
			}
			if ((!$inverse && ($pluginKey == $this->_objectControllerName || $pluginKey == $this->_controllerName)) ||
			    ($inverse && $pluginKey != $this->_objectControllerName && $pluginKey != $this->_controllerName)) {
				// loop on controller configuration
				foreach ($pluginData as $subKey => $subData) {
					if (!is_string($subKey))
						continue;
					$subData = is_array($subData) ? $subData : [$subData];
					if ($subKey == "_$type") {
						// controller-specific preplugin list
						$result = array_merge($result, $subData);
					} else if (isset($subData["_$type"]) &&
					           ($subKey == $this->_actionName ||
					            (str_starts_with($subKey, '-') && mb_substr($subKey, 1) != $this->_actionName))) {
						// action-specific plugin list, or inverse action-specific plugin list
						$list = is_array($subData["_$type"]) ? $subData["_$type"] : [$subData["_$type"]];
						$result = array_merge($result, $list);
					}
				}
				continue;
			}
		}
		TrLog::log('Trantor/Web', 'DEBUG', $result ? ("List of $type plugins: " . print_r($result, true)) : "No $type plugins.");
		return ($result);
	}
	/**
	 * Execute a plugin.
	 * @param	string	$pluginName	Name of the plugin object.
	 * @param	string	$pluginType	Type of plugin ('pre' or 'post').
	 * @return	int	Plugin execution status.
	 * @throws	\Trantor\Exceptions\Http	If the plugin doesn't exist.
	 * @throws	\Trantor\Exceptions\Flow	If the plugin throws a Flow exception.
	 */
	private function _execPlugin(string $pluginName, string $pluginType) : ?int {
		TrLog::log('Trantor/Web', 'INFO', "Executing plugin '$pluginName'.");
		$methodName = ($pluginType === 'pre') ? self::PLUGINS_PREPLUGIN_METHOD : self::PLUGINS_POSTPLUGIN_METHOD;
		// if the plugin object's name doesn't start with a backslash, prepend the default namespace
		if ($pluginName[0] != '\\') {
			$defaultNamespace = rtrim(($this->_config->defaultNamespace ?? ''), '\\');
			$pluginName = "$defaultNamespace\\$pluginName";
		}
		// check that the plugin exists
		if (!class_exists($pluginName)) {
			// can't find the object, try with the default namespace
			$defaultNamespace = $this->_config->defaultNamespace;
			$fullPluginName = "$defaultNamespace\\" . $pluginName;
			if (empty($defaultNamespace) || !class_exists($fullPluginName)) {
				TrLog::log('Trantor/Web', 'ERROR', "Plugin '$pluginName' doesn't exist.");
				throw new TrHttpException("Plugin '$pluginName' doesn't exist.", 500);
			}
			$pluginName = $fullPluginName;
		}
		// check object's type
		if (!is_subclass_of($pluginName, '\Trantor\Web\Plugin')) {
			TrLog::log('Trantor/Web', 'ERROR', "Plugin '$pluginName' is not a subclass of \\Trantor\\Web\\Plugin.");
			throw new TrHttpException("Plugin '$pluginName' is not a subclass of \\Trantor\\Web\\Plugin.", 500);
		}
		// define the plugin method that must be called
		$reflector = new \ReflectionMethod($pluginName, $methodName);
		if ($reflector->getDeclaringClass()->getName() !== ltrim($pluginName, '\\')) {
			$methodName = self::PLUGINS_PLUGIN_METHOD;
			$reflector = new \ReflectionMethod($pluginName, $methodName);
			if ($reflector->getDeclaringClass()->getName() !== ltrim($pluginName, '\\')) {
				TrLog::log('Trantor/Web', 'ERROR', "Plugin '$pluginName' has no executable '$pluginType' method.");
				throw new TrHttpException("Plugin '$pluginName' has no executable '$pluginType' method.", 500);
			}
		}
		// plugin instanciation
		$plugin = new $pluginName($this->_loader, $this->_executorController);
		// define plugin as the controller in the loader
		$this->_loader['controller'] = $plugin;
		// plugin execution
		$pluginReturn = $plugin->$methodName();
		return ($pluginReturn);
	}

	/* ********** ACTION ********** */
	/**
	 * Define the name of the action to execute.
	 * @throws	\Trantor\Exceptions\Http	If the requested action doesn't exist.
	 */
	private function _setActionName() : void {
		if (empty($this->_actionName = $this->_request->getAction()))
			$this->_actionName = self::CONTROLLERS_ROOT_ACTION;
		else if (($this->_actionName === self::PLUGINS_PREPLUGIN_METHOD ||
		          $this->_actionName === self::PLUGINS_POSTPLUGIN_METHOD ||
		          $this->_actionName === self::PLUGINS_PLUGIN_METHOD) &&
		         is_a($this->_objectControllerName, '\Trantor\Web\Plugin', true)) {
			TrLog::log('Trantor/Web', 'ERROR', "Try to execute a plugin method as an action on the controller '" . $this->_objectControllerName . "'.");
			throw new TrHttpException("Try to execute a plugin method as an action on the controller '" . $this->_objectControllerName . "'.", 500);
		}
	}

	/* ********** VIEW ********** */
	/**
	 * Load the view.
	 * @return	false|\Trantor\Web\View	Instance of the requested view, or false if the view has been disabled.
	 * @throws	\Trantor\Exceptions\Framework	If no view can be loaded.
	 * @throws	\Trantor\Exceptions\FlowQuit	If the view was explicitely deactivated in the response.
	 */
	private function _loadView() : false|\Trantor\Web\View {
		$name = $this->_response->getView();
		// manage view disabling
		if ($name === false) {
			TrLog::log('Trantor/Web', 'DEBUG', "View is disabled.");
			return (false);
		}
		// manage undefined view
		if (!$name) {
			// no defined view, use the default view
			$name = $this->_config->defaultView ?: \Trantor\Web\Config::DEFAULT_VIEW;
			TrLog::log('Trantor/Web', 'DEBUG', "Using default view '$name'.");
		}
		// manage Trantor's standard views
		if (str_starts_with($name, '~')) {
			$name = '\Trantor\Views\\' . mb_substr($name, 1);
			TrLog::log('Trantor/Web', 'DEBUG', "Using Trantor standard view '$name'.");
		}
		// force namespace
		if (!str_starts_with($name, '\\'))
			$name = "\\$name";
		// load the view
		if (class_exists($name) && is_subclass_of($name, '\Trantor\Web\View')) {
			TrLog::log('Trantor/Web', 'INFO', "Loading view '$name'.");
			return (new $name($this->_dataSources, $this->_config, $this->_response));
		}
		// the view doesn't exist
		TrLog::log('Trantor/Web', 'ERROR', "Unable to instantiate view '$name'.");
		throw new TrFrameworkException("Unable to load any view.", TrFrameworkException::NO_VIEW);
	}
	/**
	 * View init.
	 * @param	\Trantor\Web\View		$view	The view object.
	 * @throws	\Trantor\Exceptions\Framework	If no template could be used.
	 */
	private function _initView(\Trantor\Web\View $view) : void {
		if ($view->useTemplates()) {
			$template = $this->_response->getTemplate();
			if (empty($template)) {
				$controller = $this->_controllerName ? $this->_controllerName : $this->_objectControllerName;
				$action = $this->_actionName ? $this->_actionName : self::CONTROLLERS_PROXY_ACTION;
				$template = $controller . '/' . $action . self::TEMPLATE_EXTENSION;
			}
			$templatePrefix = trim(($this->_response->getTemplatePrefix() ?? ''), '/');
			if (!empty($templatePrefix))
				$template = $templatePrefix . '/' . $template;
			TrLog::log('Trantor/Web', 'DEBUG', "Initializing view '" . get_class($view) . "' with template '$template'.");
			try {
				$view->setTemplate($this->_config->templatesPath, $template);
			} catch (TrIOException $ie) {
				TrLog::log('Trantor/Web', 'ERROR', "No usable template.");
				throw new TrFrameworkException("No usable template.", TrFrameworkException::NO_TEMPLATE);
			}
		}
		$view->init();
	}
}

