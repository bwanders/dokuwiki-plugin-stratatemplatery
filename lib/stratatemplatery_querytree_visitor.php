<?php
/**
 * DokuWiki Plugin stratatemplatery
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Brend Wanders <b.wanders@utwente.nl>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die('Meh.');

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

