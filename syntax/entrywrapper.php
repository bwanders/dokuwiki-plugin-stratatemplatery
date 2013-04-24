<?php
/**
 * DokuWiki Plugin skeleton (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Brend Wanders <b.wanders@utwente.nl>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die('Meh.');

/**
 * The entry wrapper for defining what the actual data entry display in templates is.
 */
class syntax_plugin_stratatemplatery_entrywrapper extends DokuWiki_Syntax_Plugin {
    public function __construct() {
        $this->helper =& plugin_load('helper', 'templatery');
    }

    public function getType() {
        return 'container';
    }

    public function getPType() {
        return 'stack';
    }

    public function getSort() {
        return 5;
    }

    public function getAllowedTypes() {
        return array('container', 'formatting', 'substition', 'protected', 'disabled', 'paragraphs');
    }

    public function accepts($mode) {
        return $mode != 'plugin_stratatemplatery_entrywrapper' && parent::accepts($mode);
    }


    public function connectTo($mode) {
        $this->Lexer->addEntryPattern('<entry>(?=.*?</entry>)',$mode,'plugin_stratatemplatery_entrywrapper');
    }

    public function postConnect() {
        $this->Lexer->addExitPattern('</entry>','plugin_stratatemplatery_entrywrapper');
    }

    public function handle($match, $state, $pos, &$handler){
        switch($state) {
            case DOKU_LEXER_ENTER:
                // output an instruction
                return array($state);

            case DOKU_LEXER_UNMATCHED:
                // we don't care about unmatched things; just get them rendered
                $handler->_addCall('cdata', array($match), $pos);
                return false;

            case DOKU_LEXER_EXIT:
                return array($state);
        }

        return false;
    }

    public function render($mode, &$R, $data) {
        if($mode != 'xhtml') return false;

        switch($data[0]) {
            case DOKU_LEXER_ENTER:
                // handle differently when previewing template and when actually rendering
                if($this->helper->isDelegating()) {
                    // if we have the .current field, we use it, otherwise we render nothing
                    if($this->helper->hasField('.current')) {
                        $id = sectionId($this->helper->getField($mode, $R, '.current', null), $dontcare);
                        $R->doc .= '<div id="'.$id.'">';
                    }
                } else {
                    // output wrapper div and label
                    $R->doc .= '<div class="stratatemplatery-entrywrapper">';
                    $R->doc .= '<div class="stratatemplatery-entrywrapper-label">'.$this->getLang('entry_wrapper_label').'</div>';
                }
                break;

            // close div
            case DOKU_LEXER_EXIT:
                if($this->helper->isDelegating()) {
                    if($this->helper->hasField('.current')) {
                        $R->doc .= '</div>';
                    }
                } else {
                    $R->doc .= '</div>';
                }
                break;
        }


        return true;
    }
}

