<?php
/**
 * DokuWiki Plugin stratatemplatery (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Brend Wanders <b.wanders@utwente.nl>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die('Meh.');

/**
 * Syntax for template inclusion.
 */
class syntax_plugin_stratatemplatery_template extends DokuWiki_Syntax_Plugin {
    public function __construct() {
        $this->strata =& plugin_load('helper', 'stratabasic');
        $this->types =& plugin_load('helper', 'stratastorage_types');
        $this->triples =& plugin_load('helper', 'stratastorage_triples', false);
        $this->triples->initialize();

        $this->helper =& plugin_load('helper', 'templatery');
    }

    public function getType() {
        return 'baseonly';
    }

    public function getPType() {
        return 'block';
    }

    public function getSort() {
        return 295;
    }


    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{template>[^}]+?}}',$mode,'plugin_stratatemplatery_template');
    }

    public function handle($match, $state, $pos, &$handler){
        preg_match('/\{\{template>([^\}|]+?)(?:\|([^}]+?))?}}/msS',$match,$capture);
        $id = $capture[1];

        // parse variables 
        $variables = array();
        $typemap = array();
        $vars = explode('|', $capture[2]);
        $j = 0;
        for($i=0;$i<count($vars);$i++) {
            if(trim($vars[$i])=='') continue;
            // match a "property_type(hint)*= value" pattern
            // (the * is only used to indicate that the value is actually a comma-seperated list)

            if(preg_match('/^('.STRATABASIC_PREDICATE.'?)(?:_([a-z0-9]+)(?:\(([^)]+)\))?)?(\*)?\s*=(.*)$/',$vars[$i],$capture)) {
                // assign useful names
                list($match, $property, $type, $hint, $multi, $values) = $capture;

                // trim property so we don't get accidental 'name   ' keys
                $property = strtolower(utf8_trim($property));

                // determine values, splitting on commas if necessary
                if($multi == '*') {
                    $values = explode(',',$values);
                } else {
                    $values = array($values);
                }

                // generate triples from the values
                foreach($values as $v) {
                    $v = utf8_trim($v);
                    if($v == '') continue;

                    // replace the [[]] quasi-magic token with the empty string
                    if($v == '[[]]') $v = '';

                    // get type
                    if(!isset($type) || $type == '') {
                        list($type, $hint) = $this->types->getDefaultType();
                    }
                    if(!isset($typemap[$property])) {
                        $typemap[$property] = array('type'=>$type,'hint'=>($hint?:null));
                    }

                    // store normalized value
                    $variables[$property][] = $this->types->loadType($type)->normalize($v,$hint);
                }

            } else {
                $variables[$j++][] = $vars[$i];
            }
        }

        // did we include a template into a section?
        $sectioning = $this->helper->getSectioning($handler);

        return array($id, $variables, $typemap, $sectioning);
    }

    public function render($mode, &$R, $data) {
        list($id, $variables, $typemap, $sectioning) = $data;

        list($page, $hash) = $this->helper->resolveTemplate($id, $exists);

        $template = $this->helper->prepareTemplate($mode, $R, $page, $hash, $error);

        // prepare typemap
        foreach($typemap as $var=>$data) {
            $typemap[$var]['typeName'] = $data['type'];
            $typemap[$var]['type'] = $this->types->loadType($data['type']);
        }
        
        $handler = new stratatemplatery_handler($variables, $this->types, $this->triples, $typemap);

        $this->helper->renderTemplate($mode, $R, $template, $id, $page, $hash, $sectioning, $handler, $error);

        return true;
    }
}

// vim:ts=4:sw=4:et:
