<?php
/**
 * Strata Templatery, data entry plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Brend Wanders <b.wanders@utwente.nl>
 */
 
 // must be run within Dokuwiki
if (!defined('DOKU_INC')) die('Meh.');

/**
 * Data entry syntax for dedicated data blocks.
 */
class syntax_plugin_stratatemplatery_entry extends syntax_plugin_stratabasic_entry {
    function __construct() {
        parent::__construct();
        $this->templates =& plugin_load('helper', 'templatery');
    }

    function getSort() {
        return 440;
    }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<data(?: +[^#>]+?)?(?: *#[^>]*?)?>\n(?:.+?\n)*?</data>',$mode, 'plugin_stratatemplatery_entry');
    }

    function preprocess($match, &$result) {
        // did we include a template into a section?
        $sectioning = $this->templates->getSectioning($handler);

        $result['template'] = array(null, $sectioning);
        return $match;
    }

    function handleHeader($header, &$result) {
        // remove prefix and suffix
        $header = preg_replace('/(^<data)|( *>$)/','',$header);

        // extract header, and match it to get classes and fragment
        preg_match('/^( +[^#>]+)?(?: *#([^>]*?))?$/', $header, $capture);

        // find the first class with an exclamation
        foreach(preg_split('/\s+/',trim($capture[1])) as $class) {
            if($class[0] == '!') {
                $template = trim($class,'!');
                $header = str_replace($class,$template,$header);
                break;
            }
        }

        $template = trim($template);
        $result['template'][0] = $template ?: null;

        return $header;
    }

    function handleBody(&$tree, &$result) {
        $trees = $this->helper->extractGroups($tree, 'template');

        if(count($trees)) {
            $lines = $this->helper->extractText($trees[0]);
            if(count($lines)) {
                $result['template'][0] = trim($lines[0]);
            }
        }
    }


    function render($mode, &$R, $data) {
        // if the entry is broken, render and abort
        if($data == array()) {
            return parent::render($mode, $R, $data);
        }

        // otherwise, render the template
        list($id, $sectioning) = $data['template'];
        if($id != null) {
            list($page, $hash) = $this->templates->resolveTemplate($id, $exists);
            $template = $this->templates->prepareTemplate($mode, $R, $page, $hash, $error);
        } else {
            $exists = false;
        }

        // pass problems or non-xhtml renders over to the parent
        if($mode != 'xhtml' || !$exists || $error == 'template_nonexistant') {
            $result = parent::render($mode, $R, $data);

            if(!$exists || $error == 'template_nonexistant') return $result;
        }

        $typemap = array();
        $row = array();

        foreach($data['data'] as $prop=>$bucket) {
            if(count($bucket) && !isset($typemap[$prop])){
                $typemap[$prop] = array(
                    'type'=>$this->types->loadType($bucket[0]['type']),
                    'hint'=>$bucket[0]['hint']
                );
            }

            foreach($bucket as $triple) {
                $row[$prop][]=$triple['value'];
            }
        }
      
        $handler = new stratatemplatery_template_handler($row, $this->types, $this->triples, $typemap);

        $this->templates->renderTemplate($mode, $R, $template, $id, $page, $hash, $sectioning, $handler, $error);

        return true;
    }
}
