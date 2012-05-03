<?php
/**
 * DokuWiki Plugin stratatemplatery (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Brend Wanders <b.wanders@utwente.nl>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die('Meh.');

// FIXME: Should be removed once it can be autoloaded
require_once DOKU_PLUGIN.'stratastorage/strata_querytree_visitor.php';

// Load the templatery handler
require_once DOKU_PLUGIN.'stratatemplatery/stratatemplatery_handler.php';

class action_plugin_stratatemplatery extends DokuWiki_Action_Plugin {
    function __construct() {
        $this->triples =& plugin_load('helper', 'stratastorage_triples', false);
        $this->triples->initialize();

        $this->helper =& plugin_load('helper', 'templatery');
    }

    /**
     * Register function called by DokuWiki to allow us
     * to register events we're interested in.
     *
     * @param controller object the controller to register with
     */
    public function register(Doku_Event_Handler &$controller) {
        $controller->register_hook('STRATABASIC_PREPARE_QUERY', 'BEFORE', $this, '_stratabasic_prepare_query');
    }

    public function _stratabasic_prepare_query(&$event, $param) {
        if($this->helper->isDelegating()) {
            $query =& $event->data;
            $visitor = new stratatemplatery_querytree_visitor($this->helper);
            $visitor->visit($query);
        }
    }
}

class stratatemplatery_querytree_visitor extends strata_querytree_visitor {
    function __construct(&$helper) {
        $this->helper = $helper;
    }

    function visit_atom(&$a) {
        if($a['type'] == 'literal' && substr($a['text'],0,2) == '@@' && substr($a['text'],-2) == '@@') {
            $a['text'] = $this->helper->getField('metadata', $blork, substr($a['text'],2,-2), $a['text']);
        }
    }

    /**
     * Visits a triple pattern.
     */
    function visit_tp(&$tp) {
        parent::visit_tp($tp);

        $this->visit_atom($tp['subject']);
        $this->visit_atom($tp['predicate']);
        $this->visit_atom($tp['object']);
    }

    /**
     * Visit a filter pattern.
     */
    function visit_fp(&$fp) {
        parent::visit_fp($fp);

        $this->visit_atom($fp['rhs']);
        $this->visit_atom($fp['lhs']);
    }
}
