<?php

/**
 * View
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2023-2024, Amaury Bouchard
 * @link	https://www.trantor.org/en/documentation/helper-attr_view
 */

namespace Trantor\Attributes;

use \Trantor\Base\Log as TrLog;

/**
 * Attribute used to define the view used by a controller or an action.
 *
 * Examples:
 * - Tell Trantor to use the \Trantor\Views\Json view on all actions of a controller:
 * use \Trantor\Attributes\View as TrView;
 *
 * #[TrView('\Trantor\Views\Json')]
 * class SomeController extends \Trantor\Web\Controller {
 *     public function someAction() { }
 * }
 *
 * - The same, but only for one action of the controller:
 * use \Trantor\Attributes\View as TrView;
 *
 * class SomeController extends \Trantor\Web\Controller {
 *     #[TrView('\Trantor\Views\Json')]
 *     public function someAction() { }
 * }
 * 
 * - The same (written differently):
 * #[TrView(\Trantor\Views\Json::class)]
 *
 * - The same (telling to use Trantor's standard Json view):
 * #[TrView('~Json')]
 *
 * - Tell Trantor to use the standard RSS view:
 * #[TrView('~Rss')]
 *
 * - Reset to the default view (as configured in the 'trantor.json' configuration file):
 * #[TrView]
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class View extends \Trantor\Web\Attribute {
	/**
	 * Constructor.
	 * @param	null|false|string	$view	(optional) The fully-namespaced name of the view object to use.
	 *						If left empty (or set to null), use the default view as configured in the 'trantor.json' configuration file.
	 *						If set to false, disable the processing of the view.
	 */
	public function __construct(null|false|string $view=null) {
		$this->_getResponse()->setView($view);
	}
}

