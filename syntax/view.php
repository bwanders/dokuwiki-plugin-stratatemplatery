<?php
/**
 * Strata Templatery, view plugin
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
require_once DOKU_PLUGIN.'stratatemplatery/templatery_handler.php';
 
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

        $typemap = array();
        foreach($data['fields'] as $meta) {
            if(!isset($typemap[$meta['variable']])) {
                $typemap[$meta['variable']] = array(
                    'type'=>$this->types->loadType($meta['type']),
                    'hint'=>$meta['hint']
                );
            }
        }
       
        foreach($result as $row) {
            $handler = new stratatemplatery_template_handler($row, $this->types, $this->triples, $typemap);

            $this->templates->renderTemplate($mode, $R, $template, $id, $page, $hash, $sectioning, $handler, $error);
        }
        $result->closeCursor();

        return false;
    }
}

