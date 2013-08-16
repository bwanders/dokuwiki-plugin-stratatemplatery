<?php
/**
 * DokuWiki Plugin Strata Templatery (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Brend Wanders <b.wanders@utwente.nl>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die('Meh.');

class syntax_plugin_stratatemplatery_fields extends syntax_plugin_templatery_native {
    function __construct() {
        parent::__construct();
        $this->util =& plugin_load('helper', 'strata_util');
        $this->triples =& plugin_load('helper', 'strata_triples');
    }

    public function getType() {
        return 'container';
    }

    /**
     * The name of the native template. This is used to determine the
     * required syntax. (i.e., the syntax is @@->name@@).
     */ 
    protected function getName() {
        return 'fields(?:\|(?:only|exclude)=.+?)?';
    }

    public function handle($match, $state, $pos, &$handler){
        preg_match('/@@->fields(?:\|(only|exclude)=(.+?))?@@/', $match, $m);

        if(empty($m[1])) return array();

        $result = array();
        $result['command'] = $m[1];
        $result['fields'] = array_map('trim', explode(',', $m[2]));
        return $result;
    }
   
    public function render($mode, &$R, $data) {
        if($this->isPreview() && $mode == 'xhtml') {
           $R->doc .= '<span class="templatery-include">&#8594;fields';
            if(!empty($data)) {
                $R->doc .= ' <span class="value-separator">&#187;</span> ';
                $R->doc .= $R->_xmlEntities($data['command']) . ' ';
                $R->doc .= implode(', ', array_map(array($R, '_xmlEntities'), $data['fields']));
            }
            $R->doc .= '</span>';
            return true;
        }

        // construct list of fields to display
        $fields = array();

        // check if there is a command
        if(empty($data)) {
            $data['command'] = 'exclude';
            $data['fields'] = array();
        }

        // determine selected fields
        if($data['command'] == 'only') {
            $fields = $data['fields'];
        } elseif($data['command'] == 'exclude') {
            $excludes = array_map('strtolower', $data['fields']);
            foreach($this->listFields() as $field) {
                // skip isa and title keys
                if($field == $this->util->getTitleKey() || $field == $this->util->getIsaKey()) continue;

                // skip any field starting with '.'
                if($field{0} == '.') continue;

                // skip all fields that are excluded
                if(in_array(strtolower($field), $excludes)) continue;

                $fields[] = $field;
            }
        }

        if(!$this->isPreview()) {
            $R->table_open();
            
            // render a row for each key, displaying the values as comma-separated list
            foreach($fields as $field) {
                if($this->hasField($field)) {
                    // render row header
                    $R->tablerow_open();
                    $R->tableheader_open();
                    $this->util->renderPredicate($mode, $R, $this->triples, $field);
                    $R->tableheader_close();

                    // render row content
                    $R->tablecell_open();
                    $this->displayField($mode, $R, $field, '');
                    $R->tablecell_close();
                    $R->tablerow_close();
                }
            }

            $R->table_close();

            return true;
        }

        return false;
    }
}

