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
require_once DOKU_PLUGIN.'stratabasic/syntax/entry.php';
require_once DOKU_PLUGIN.'stratatemplatery/templatery_handler.php';
 
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
        $this->Lexer->addSpecialPattern('<xdata(?: +[^#>]+?)?(?: *#[^>]*?)?>\n(?:.+?\n)*?</xdata>',$mode, 'plugin_stratatemplatery_entry');
    }

    function preprocess($match, &$result) {
        // did we include a template into a section?
        $sectioning = $this->templates->getSectioning($handler);

        $result['template'] = array(null, $sectioning);
        return $match;
    }

    function handleHeader($header, &$result) {
        // remove prefix and suffix
        return preg_replace('/(^<xdata)|( *>$)/','',$header);
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
        list($id, $sectioning) = $data['template'];
        list($page, $hash) = $this->templates->resolveTemplate($id, $exists);

        // pass problems or non-xhtml renders over to the parent
        // we want our data to be stored
        if($data == array() || $mode != 'xhtml' || !$exists) {
            return parent::render($mode, $R, $data);
        }

        if($data != array()) {
            $template = $this->templates->prepareTemplate($mode, $R, $page, $hash, $error);
    
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
        }
        return true;
    }
}
