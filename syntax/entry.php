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
class syntax_plugin_stratatemplatery_entry extends syntax_plugin_strata_entry {
    function __construct() {
        parent::__construct();
        $this->templates =& plugin_load('helper', 'templatery');
    }

    function getSort() {
        return parent::getSort() - 1;
    }

    function connectTo($mode) {
        if($this->getConf('enable_entry')) {
            $this->Lexer->addSpecialPattern('<data(?: +[^#>]+?)?(?: *#[^>]*?)?>\n(?:.*?\n)*?</data>',$mode, 'plugin_stratatemplatery_entry');
        }
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

        $classes = preg_split('/\s+/', trim($capture[1]));

        // find the first class with an exclamation
        foreach($classes as $class) {
            if($class[0] == '!') {
                $template = trim($class,'!');
                $header = str_replace($class,$template,$header);
                break;
            }
        }

        // if there was not explicit class focus, we try the first
        if(empty($template)) {
            $template = $classes[0];
        }

        $template = trim($template);
        $result['template'][0] = $template ?: null;

        return $header;
    }

    function handleBody(&$tree, &$result) {
        $trees = $this->syntax->extractGroups($tree, 'template');

        if(count($trees)) {
            $lines = $this->syntax->extractText($trees[0]);
            if(count($lines)) {
                $result['template'][0] = trim($lines[0]['text']);
            }
        }
    }


    function render($mode, &$R, $data) {
        global $ID;

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

        // create '.subject' field 
        $subject = $ID.'#'.$data['entry'];

        // resolve the subject to normalize everything
        resolve_pageid(getNS($ID),$subject,$exists);

        // create 'entry title' field
        $titleKey = $this->util->normalizePredicate($this->util->getTitleKey());

        $fixTitle = false;

        // determine the title if we are rendering XHTML
        if(empty($data['data'][$titleKey]) && $mode == 'xhtml') {
            $titleTriples = $this->triples->fetchTriples($subject,$titleKey);
            if(count($titleTriples)) {
                $data['data'][$titleKey][] = array(
                    'value'=>$titleTriples[0]['object'],
                    'type'=>'text',
                    'hint'=>null
                );
            }
        } elseif(empty($data['data'][$titleKey])) {
            if(!empty($data['title candidate'])) {
                // we have a candidate from somewhere
                $data['data'][$titleKey][] = $data['title candidate'];
            } elseif(!empty($R->meta['title'])) {
                // we do not have a candidate, so we use the page title
                // (this is possible because fragments set the candidate)
                $data['data'][$titleKey][] = array(
                    'value'=>$R->meta['title'],
                    'type'=>'text',
                    'hint'=>null
                );
            }
        }

        $typemap = array();
        $row = array();

        foreach($data['data'] as $prop=>$bucket) {
            $prop = strtolower($prop);
            if(count($bucket) && !isset($typemap[$prop])){
                $typemap[$prop] = array(
                    'type'=>$this->util->loadType($bucket[0]['type']),
                    'typeName'=>$bucket[0]['type'],
                    'hint'=>$bucket[0]['hint']
                );
            }

            foreach($bucket as $triple) {
                $row[$prop][]=$triple['value'];
            }
        }

        // store '.subject' field 
        $row['.subject'][] = $subject;
        $typemap['.subject'] = array(
            'type'=>$this->util->loadType('ref'),
            'typeName'=>'ref',
            'hint'=>null
        );


      
        $handler = new stratatemplatery_handler($row, $this->util, $this->triples, $typemap);

        $this->templates->renderTemplate($mode, $R, $template, $id, $page, $hash, $sectioning, $handler, $error);

        return true;
    }
}
