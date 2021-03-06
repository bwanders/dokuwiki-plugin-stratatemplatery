<?php
/**
 * Strata Templatery, view plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Brend Wanders <b.wanders@utwente.nl>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die('Meh.');

/**
 * Templated view.
 */
class syntax_plugin_stratatemplatery_view extends syntax_plugin_strata_select {
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
                $result['template'][0] = trim($lines[0]['text']);
            }
        }
    }

    function preprocess($match, $state, $pos, &$handler, &$result, &$typemap) {
        // did we include a template into a section?
        $sectioning = $this->templates->getSectioning($handler);

        $result['template'] = array(null, $sectioning);
        return $match;
    }

    function render($mode, Doku_Renderer $R, $data) {
        if($data == array() || isset($data['error'])) {
            if($mode == 'xhtml') {
                $this->displayError($R, $data);
            }

            return;
        }

        $query = $this->prepareQuery($data['query']);

        // execute the query
        $result = $this->triples->queryRelations($query);

        if($result == false) {
            return;
        }

        list($id, $sectioning) = $data['template'];

        list($page, $hash) = $this->templates->resolveTemplate($id, $exists);

        $template = $this->templates->prepareTemplate($mode, $R, $page, $hash, $error);
        // handle error
        if($error != null) {
            if($mode == 'xhtml') {
                $data['error']['message'] = sprintf($this->templates->getLang($error),$id);
                msg($data['error']['message'], -1);
                $this->displayError($R, $data);
            }
            return false;
        }

        $typemap = array();
        foreach($data['fields'] as $meta) {
			$meta['variable'] = strtolower($meta['variable']);
            if(!isset($typemap[$meta['variable']])) {
                $typemap[$meta['variable']] = array(
                    'type'=>$this->util->loadType($meta['type']),
                    'typeName'=>$meta['type'],
                    'hint'=>$meta['hint']
                );
            }
        }

        $sections = $this->_templateHasSections($template);

        if(!$sections) {
            $this->ui_container_open($mode, $R, $data, array('strata-container', 'strata-container-view'));
            $this->util->renderCaptions($mode, $R, $data['fields']);
        }

        $itemcount = 0;
        foreach($result as $row) {
			$values = array();
			foreach($row as $key=>$value) {
				$values[strtolower($key)] = $value;
			}
            $handler = new stratatemplatery_handler($values, $this->util, $this->triples, $typemap);

            if($mode == 'xhtml' && !$sections) $R->doc .= '<div class="strata-item" data-strata-order="'.($itemcount++).'">'.DOKU_LF;
            $this->templates->renderTemplate($mode, $R, $template, $id, $page, $hash, $sectioning, $handler, $error);
            if($mode == 'xhtml' && !$sections) $R->doc .= '</div>'.DOKU_LF;
        }

        if(!$sections) $this->ui_container_close($mode, $R);

        $result->closeCursor();

        return false;
    }

    /**
     * Check the given template for sections.
     */
    function _templateHasSections(&$template) {
		// abort if template is a failure
		if($template == null) return false;

        foreach($template as $ins) {
            if($ins[0] == 'plugin' && $ins[1][0] == 'templatery_section') return true;
        }

        return false;
    }
}
