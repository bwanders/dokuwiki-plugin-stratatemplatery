<?php
/**
 * DokuWiki Plugin stratatemplatery (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Brend Wanders <b.wanders@utwente.nl>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die('Meh.');

class action_plugin_stratatemplatery extends DokuWiki_Action_Plugin {
    function __construct() {
        $this->triples =& plugin_load('helper', 'strata_triples');
        $this->helper =& plugin_load('helper', 'templatery');
    }

    /**
     * Register function called by DokuWiki to allow us
     * to register events we're interested in.
     *
     * @param controller object the controller to register with
     */
    public function register(Doku_Event_Handler &$controller) {
        $controller->register_hook('STRATA_PREPARE_QUERY', 'BEFORE', $this, '_strata_prepare_query');
    }

    public function _strata_prepare_query(&$event, $param) {
        if($this->helper->isDelegating()) {
            $query =& $event->data;
            $visitor = new stratatemplatery_querytree_visitor($this->helper);
            $visitor->visit($query);
        }
    }
}

/**
 * Strata templatery 'pluggable' autoloader. This function is responsible
 * for autoloading classes.
 *
 * @param fullname string the name of the class to load
 */
function plugin_stratatemplatery_autoload($fullname) {
    static $classes = null;
    if(is_null($classes)) $classes = array(
        'stratatemplatery_handler' => DOKU_PLUGIN.'stratatemplatery/lib/stratatemplatery_handler.php',
        'stratatemplatery_querytree_visitor' => DOKU_PLUGIN.'stratatemplatery/lib/stratatemplatery_querytree_visitor.php',
   );

    if(isset($classes[$fullname])) {
        require_once($classes[$fullname]);
        return;
    }
}

// register autoloader with SPL loader stack
spl_autoload_register('plugin_stratatemplatery_autoload');

