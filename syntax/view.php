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

class stratatemplatery_template_handler implements templatery_handler {
    public function __construct($variables, &$types, &$triples, $typemap) {
        $this->vars = $variables;
        $this->types = $types;
        $this->triples = $triples;
        $this->typemap = $typemap;
    }

    protected function parseField($field) {
        if(preg_match('/^(?:\s*('.STRATABASIC_VARIABLE.'))(?:@([a-z0-9]*)(?:\(([^\)]*)\))?)?(?:_([a-z0-9]*)(?:\(([^\)]*)\))?)?\s*$/',$field,$capture)) {
            list(, $variable, $agg, $agghint, $type, $hint) = $capture;
            return array('variable'=>$variable, 'aggregate'=>($agg?:null), 'aggregateHint'=>($agg?$agghint:null), 'type'=>$type, 'hint'=>$hint);
        }

        return array('variable'=>$field);
    }

    public function has($var) {
        return isset($this->vars[$var]) && $this->vars[$var] != array();
    }

    public function hasField($field) {
        $field = $this->parseField($field);
        $var = $field['variable'];

        return $this->has($var);
    }

    public function getField($mode, &$R, $field, $default=null) {
        $field = $this->parseField($field);
        $var = $field['variable'];

        return $this->has($var) ? join(', ',$this->vars[$var]) : $default;
    }

    public function displayField($mode, &$R, $field, $default=null) {
        $field = $this->parseField($field);
        $var = $field['variable'];

        $values = $this->has($var) ? $this->vars[$var] : ($default==null?array():array($default));
        $defaults = $this->typemap[$var];

        if(isset($field['type'])) {
            $type = $this->types->loadType($field['type']);
            $hint = $field['hint'];
        } else {
            $type = $defaults['type'];
            $hint = $default['hint'];
        }

        $aggregate = $this->types->loadAggregate($field['aggregate']);
        $aggergateHint = $field['aggregateHint'];

        $values = $aggregate->aggregate($values, $aggregateHint);

        if($values != array()) {
            $firstvalue = true;
            foreach($values as $value) {
                if(!$firstvalue) $R->doc .= ', ';
                $type->render($mode, $R, $this->triples, $value, $hint);
                $firstvalue = false;
            }
        }

        return true;
    }
}

