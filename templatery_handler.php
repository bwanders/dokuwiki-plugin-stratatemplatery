<?php
/**
 * Strata Templatery template handler
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Brend Wanders <b.wanders@utwente.nl>
 */
 
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

require_once DOKU_PLUGIN.'templatery/templatery_handler.php';

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
			if(isset($defaults)) {
	            $type = $defaults['type'];
    	        $hint = $default['hint'];
			} else {
				list($type,$hint) = $this->types->getDefaultType();
				$type = $this->types->loadType($type);
			}
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

