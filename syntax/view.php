<?php
/**
 * Strata Basic, data entry plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Brend Wanders <b.wanders@utwente.nl>
 */
 
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'syntax.php';
require_once DOKU_PLUGIN.'templatery/templatery_handler.php';
 
/**
 * Templated view.
 */
class syntax_plugin_stratatemplatery_view extends syntax_plugin_stratabasic_select {
    function __construct() {
        parent::__construct();
        $this->templates =& plugin_load('helper', 'templatery');
   }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<view'.$this->helper->fieldsShortPattern().'* *>\n.+?\n</view>',$mode, 'plugin_stratatemplatery_view');
    }

    function handleHeader($header, &$result, &$typemap) {
        return preg_replace('/(^<view)|( *>$)/','',$header);
    }

    function handleBody(&$tree, &$result, &$typemap) {
        $trees = $this->helper->extractGroups($tree, 'template');

        if(count($trees)) {
            $lines = $this->helper->extractText($trees[0]);
            if(count($lines)) {
                $result['template'][0] = trim($lines[0]);
            }
        }
    }

    function preprocess($match, &$handler, &$result, &$typemap) {
        // did we include a template into a section?
        $sectioning = $this->templates->getSectioning($handler);

        $result['template'] = array(null, $sectioning);
        return $match;
    }

/*
    public function render($mode, &$R, $data) {
        list($id, $variables, $sectioning) = $data;

        list($page, $hash) = $this->helper->resolveTemplate($id, $exists);

        $template = $this->helper->prepareTemplate($mode, $R, $page, $hash, $error);
        
        $handler = new templatery_template_handler($variables);

        $this->helper->renderTemplate($mode, $R, $template, $id, $page, $hash, $sectioning, $handler, $error);

        return true;
    }
*/

    function render($mode, &$R, $data) {
        if($data == array()) {
            return;
        }

        // execute the query
        $result = $this->triples->queryRelations($data['query']);

        if($result == false) {
            return;
        }

        list($id, $sectioning) = $data['template'];

        list($page, $hash) = $this->templates->resolveTemplate($id, $exists);

        $template = $this->templates->prepareTemplate($mode, $R, $page, $hash, $error);
       
/* 
        // prepare all 'columns'
        $fields = array();
        foreach($data['fields'] as $meta) {
            $fields[] = array(
                'variable'=>$meta['variable'],
                'type'=>$this->types->loadType($meta['type']),
                'hint'=>$meta['hint'],
                'aggregate'=>$this->types->loadAggregate($meta['aggregate']),
                'aggergateHint'=>$meta['aggregateHint']
            );
        }
*/

        foreach($result as $row) {
            $handler = new stratatemplatery_template_handler($row);

            $this->templates->renderTemplate($mode, $R, $template, $id, $page, $hash, $sectioning, $handler, $error);
        }
        $result->closeCursor();

/*
        foreach($fields as $f) {
            $values = $f['aggregate']->aggregate($row[$f['variable']], $f['aggregateHint']);
            $firstValue = true;
            foreach($values as $value) {
                if(!$firstValue) $R->doc .= ', ';
                $f['type']->render($mode, $R, $this->triples, $value, $f['hint']);
                $firstValue = false;
            }
*/


        return false;
    }
}

class stratatemplatery_template_handler implements templatery_handler {
    public function __construct($variables) {
        $this->vars = $variables;
    }

    public function hasField($field) {
        return isset($this->vars[$field]) && $field != array();
    }

    public function getField($mode, &$R, $field, $default=null) {
        return $this->hasField($field) ? join(', ',$this->vars[$field]) : $default;
    }

    public function displayField($mode, &$R, $field, $default=null) {
        if($mode != 'xhtml') return false;

        $value = $this->hasField($field) ? $this->vars[$field] : array($default);

        if($value != array()) {
            $firstvalue = true;
            foreach($value as $v) {
                if(!$firstvalue) $R->doc .= ', ';
                $R->doc .= $R->_xmlEntities($v);
                $firstvalue = false;
            }
        }

        return true;
    }
}

