<?php
/**
 * Strata Templatery, list view plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Brend Wanders <b.wanders@utwente.nl>
 */
 
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die('Meh.');

/**
 * Templated list view.
 */
class syntax_plugin_stratatemplatery_list extends syntax_plugin_stratabasic_select {
    function __construct() {
        parent::__construct();
        $this->templates =& plugin_load('helper', 'templatery');
    }

    public function getSort() {
        return parent::getSort() - 1;
    }

    public function getAllowedTypes() {
        return array('formatting','substition','disabled','protected');
    }

    function connectTo($mode) {
        $this->Lexer->addEntryPattern('<list'.$this->helper->fieldsShortPattern().'* *>\n.+?\n<template>(?=.*?</list>)',$mode, 'plugin_stratatemplatery_list');
    }

    function postConnect() {
        $this->Lexer->addExitPattern('</list>', 'plugin_stratatemplatery_list');
    }

    function handle($match, $state, $pos, &$handler) {
        switch($state) {
            case DOKU_LEXER_ENTER:
                $capturer = new StrataTemplatery_Handler_Inline_Capture($handler->CallWriter, parent::handle($match, $state, $pos, &$handler));
                $handler->CallWriter =& $capturer;
                return false;

            case DOKU_LEXER_UNMATCHED:
                $handler->_addCall('cdata', array($match), $pos);
                return false;

            case DOKU_LEXER_EXIT:
                $capturer =& $handler->CallWriter;
                $handler->CallWriter =& $capturer->CallWriter;
                $result =& $capturer->data;
                $result['template'] = $capturer->process();

                return $result;
        }
    }

    function handleHeader($header, &$result, &$typemap) {
        return preg_replace('/(^<list)|( *>$)/','',$header);
    }

    function render($mode, &$R, $data) {
        if($data == array()) {
            return;
        }

        $query = $this->prepareQuery($data['query']);

        // execute the query
        $result = $this->triples->queryRelations($query);

        if($result == false) {
            return;
        }

        $template = $data['template'];

        $typemap = array();
        foreach($data['fields'] as $meta) {
			$meta['variable'] = strtolower($meta['variable']);
            if(!isset($typemap[$meta['variable']])) {
                $typemap[$meta['variable']] = array(
                    'type'=>$this->types->loadType($meta['type']),
                    'hint'=>$meta['hint']
                );
            }
        }

        $R->doc .= '<div class="stratabasic-list">'.DOKU_LF;
        $R->listu_open();
        foreach($result as $row) {
            $R->listitem_open(1);
            $R->listcontent_open();
			$values = array();
			foreach($row as $key=>$value) {
				$values[strtolower($key)] = $value;
			}
            $handler = new stratatemplatery_handler($values, $this->types, $this->triples, $typemap);

            $this->templates->applyTemplate($template, $handler, $R);
            $R->listcontent_close();
            $R->listitem_close();
        }
        $R->listu_close();
        $R->doc .= '</div>'.DOKU_LF;
        $result->closeCursor();

        return false;
    }
}

class StrataTemplatery_Handler_Inline_Capture {
    var $CallWriter;
    var $calls = array();
    var $data;

    function __construct(&$callWriter, &$data) {
        $this->CallWriter =& $callWriter;
        $this->data =& $data;
    }

    function writeCall($call) {
        $this->calls[] = $call;
    }

    function writeCalls($calls) {
        $this->calls = array_merge($this->calls, $calls);
    }

    function finalise() {
        // Noop. This shouldn't be required, ever.
    }

    function process() {
        $result = array();

        // compact cdata instructions
        for($i=0;$i<count($this->calls);$i++) {
            $call = $this->calls[$i];
            $key = count($result);
            if($key && $call[0] == 'cdata' && $result[$key-1][0] == 'cdata') {
                $result[$key-1][1][0] .= $call[1][0];
            } else {
                $result[] = $call;
            }
        }

        return $result;
    }
}
