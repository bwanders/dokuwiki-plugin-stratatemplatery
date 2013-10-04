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
class syntax_plugin_stratatemplatery_listview extends syntax_plugin_strata_select {
    function __construct() {
        parent::__construct();
        $this->templates =& plugin_load('helper', 'templatery');
    }

    function getType() {
        return 'container';
    }

    function getPType() {
        return 'block';
    }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<view:list'.$this->helper->fieldsShortPattern().'* *>\n.+?\n</view>',$mode, 'plugin_stratatemplatery_listview');
    }

    function handleHeader($header, &$result, &$typemap) {
        return preg_replace('/(^<view:list)|( *>$)/','',$header);
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

    function fixTemplate(&$template) {
        // check start and end
        $start = !empty($template[0]) && ($template[0][0] == 'listu_open' ||  $template[0][0] == 'listo_open');
        $end =   !empty($template[count($template)-1]) && ($template[count($template)-1][0] == 'listu_close' ||  $template[count($template)-1][0] == 'listo_close');

        if(!$start || !$end) {
            return false;
        }
        
        // check whether it goes below 1 (i.e. we leave the overall list)
        $counter = 1;
        for($i = 1; $i < count($template)-1; $i++) {
            $ins = $template[$i];

            if($ins[0] == 'listu_open' || $ins[0] == 'listo_open') {
                $counter++;
            } elseif($ins[0] == 'listu_close' || $ins[0] == 'listo_close') {
                $counter--;
            }

            if($counter == 0) {
                return false;
            }
        }

        // if both start and end are correct, and we never leave the list in between
        // we have a good list item, and will correct it
        return array_slice($template, 2, -2);
    }

    function render($mode, &$R, $data) {
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
        $template = $this->fixTemplate($template);

        if($template == false) {
            if($mode == 'xhtml') {
                $data['error']['message'] = $this->getLang('error_template_not_listitem');
                msg($data['error']['message'],-1);
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



        if($mode == 'xhtml') $R->doc .= '<div class="strata-container strata-container-list">'.DOKU_LF;

        $this->util->renderCaptions($mode, $R, $data['fields']);

        $R->listu_open(); 
        $itemcount = 0;
        foreach($result as $row) {
			$values = array();
			foreach($row as $key=>$value) {
				$values[strtolower($key)] = $value;
			}
            $handler = new stratatemplatery_handler($values, $this->util, $this->triples, $typemap);

            $R->doc .= '<li class="level1 strata-item" data-strata-order="'.($itemcount++).'">'.DOKU_LF;
            $this->templates->renderTemplate($mode, $R, $template, $id, $page, $hash, $sectioning, $handler, $error);
            $R->doc .= '</li>'.DOKU_LF;
        }
        $R->listu_close();

        if($mode == 'xhtml') $R->doc .= '</div>'.DOKU_LF;

        $result->closeCursor();

        return false;
    }
}

