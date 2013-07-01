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
class syntax_plugin_stratatemplatery_tableview extends syntax_plugin_strata_select {
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
        $this->Lexer->addSpecialPattern('<view:table'.$this->helper->fieldsShortPattern().'* *>\n.+?\n</view>',$mode, 'plugin_stratatemplatery_tableview');
    }

    function handleHeader($header, &$result, &$typemap) {
        return preg_replace('/(^<view:table)|( *>$)/','',$header);
    }

    function handleBody(&$tree, &$result, &$typemap) {
        $trees = $this->helper->extractGroups($tree, 'template');

        if(count($trees)) {
            $lines = $this->helper->extractText($trees[0]);
            if(count($lines)==1) {
                $result['template'][0]['row'] = trim($lines[0]['text']);
            } elseif(count($lines) == 2) {
                $result['template'][0]['header'] = trim($lines[0]['text']);
                $result['template'][0]['row'] = trim($lines[1]['text']);
            } elseif(count($lines) == 3) {  
                $result['template'][0]['header'] = trim($lines[0]['text']);
                $result['template'][0]['row'] = trim($lines[1]['text']);
                $result['template'][0]['footer'] = trim($lines[2]['text']);
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
        if($template == null) return null;

        // check start and end
        $start = !empty($template[0]) && ($template[0][0] == 'table_open');
        $end =   !empty($template[count($template)-1]) && $template[count($template)-1][0] == 'table_close';

        if(!$start || !$end) {
            return false;
        }
        
        // check whether it goes below 1 (i.e. we leave the overall list)
        $counter = 1;
        for($i = 1; $i < count($template)-1; $i++) {
            $ins = $template[$i];

            if($ins[0] == 'table_open') {
                $counter++;
            } elseif($ins[0] == 'table_close') {
                $counter--;
            }

            if($counter == 0) {
                return false;
            }
        }

        // if both start and end are correct, and we never leave the list in between
        // we have a good table body, and will correct it
        return array_slice($template, 1, -1);
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

        $templates = array();
        list($ids, $sectioning) = $data['template'];

        // check to see if we need fallbacks
        if(count($ids) == 1 && !empty($ids['row']) && !strpos($ids['row'], '#')) {
            $fallbacks = array(
                'header' => $ids['row'].'#header',
                'footer' => $ids['row'].'#footer'
            );
        }

        // load templates
        foreach(array('header', 'row', 'footer') as $name) {
            if(empty($ids[$name]) && empty($fallbacks[$name])) continue;
            $templates[$name] = array();

            $id = !empty($ids[$name]) ? $ids[$name] : $fallbacks[$name];

            list($page, $hash) = $this->templates->resolveTemplate($id, $exists);

            $error = null;
            $template = $this->templates->prepareTemplate($mode, $R, $page, $hash, $error);

            // handle error
            if($error != null && !empty($ids[$name])) {
                if($mode == 'xhtml') {
                    $data['error']['message'] = sprintf($this->templates->getLang($error),$id);
                    msg($data['error']['message'], -1);
                    $this->displayError($R, $data);
                }
                return false;
            }

            if($error == null) {
                $templates[$name]['template'] = $this->fixTemplate($template);
                $templates[$name]['page'] = $page;
                $templates[$name]['hash'] = $hash;

                // stash actually used id
                if($templates[$name]['template'] != null && empty($ids[$name])) {
                    $ids[$name] = $fallbacks[$name];
                }

                // error if explicit template is used
                if($templates[$name]['template'] == null && !empty($ids[$name])) {
                    if($mode == 'xhtml') {
                        $data['error']['message'] = $this->getLang('error_'.$name.'_template_not_table');
                        msg($data['error']['message'],-1);
                        $this->displayError($R, $data);
                    }

                    return false;
                }
            }
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

        // clear out errors
        $error = null;

        if($mode == 'xhtml') $R->doc .= '<div class="strata-container strata-container-table">'.DOKU_LF;

        $this->util->renderCaptions($mode, $R, $data['fields']);

        $R->table_open();
        if(!empty($templates['header'])) {
            $handler = new stratatemplatery_handler(array(), $this->util, $this->triples, array());
            if($mode == 'xhtml') $R->doc .= '<thead>'.DOKU_LF;
            $this->templates->renderTemplate($mode, $R, $templates['header']['template'], $ids['header'], $templates['header']['page'], $templates['header']['hash'], $sectioning, $handler, $error);
            if($mode == 'xhtml') $R->doc .= '</thead>'.DOKU_LF;
        }
        foreach($result as $row) {
			$values = array();
			foreach($row as $key=>$value) {
				$values[strtolower($key)] = $value;
			}
            $handler = new stratatemplatery_handler($values, $this->util, $this->triples, $typemap);

            if($mode == 'xhtml') $R->doc .= '<tbody class="strata-item">'.DOKU_LF;
            $this->templates->renderTemplate($mode, $R, $templates['row']['template'], $ids['row'], $templates['row']['page'], $templates['row']['hash'], $sectioning, $handler, $error);
            if($mode == 'xhtml') $R->doc .= '</tbody>'.DOKU_LF;
        }
        if(!empty($templates['footer'])) {
            $handler = new stratatemplatery_handler(array(), $this->util, $this->triples, array());
            if($mode == 'xhtml') $R->doc .= '<tfoot>'.DOKU_LF;
            $this->templates->renderTemplate($mode, $R, $templates['footer']['template'], $ids['footer'], $templates['footer']['page'], $templates['footer']['hash'], $sectioning, $handler, $error);
            if($mode == 'xhtml') $R->doc .= '</tfoot>'.DOKU_LF;
        }
        $R->table_close();

        if($mode == 'xhtml') $R->doc .= '</div>'.DOKU_LF;

        $result->closeCursor();

        return false;
    }
}

