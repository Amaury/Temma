<?php

/**
 * autoload.php
 *
 * This file is used for testing purposes with PHPUnit (see documentation).
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2023-2024, Amaury Bouchard
 * @link	https://www.trantor.org/en/documentation/tests
 */

// include path configuration
set_include_path(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . PATH_SEPARATOR . get_include_path());

// Trantor autoloader init
require_once('Trantor/Base/Autoload.php');
\Trantor\Base\Autoload::autoload();

// Composer autoloader
@include_once(__DIR__ . '/../vendor/autoload.php');

// log init
use \Trantor\Base\Log as TrLog;
TrLog::logToStdOut();

