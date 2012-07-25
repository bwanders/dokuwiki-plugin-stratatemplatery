<?php
/**
 * Strata Templatery template handler
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Brend Wanders <b.wanders@utwente.nl>
 */
 
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die('Meh.');

require_once DOKU_PLUGIN.'templatery/templatery_handler.php';

/**
 * This templatery handler provides typed functionality.
 */
class stratatemplatery_handler implements templatery_handler {
    public function __construct($variables, &$types, &$triples, $typemap) {
        $this->vars = $variables;
        $this->types = $types;
        $this->triples = $triples;
        $this->typemap = $typemap;
    }

    /**
     * Splits a field into aggregation, type and accompanying hints.
     */
    protected function parseField($field) {
        if(preg_match('/^(?:\s*('.STRATABASIC_PREDICATE.'))(?:@([a-z0-9]*)(?:\(([^\)]*)\))?)?(?:_([a-z0-9]*)(?:\(([^\)]*)\))?)?\s*$/',$field,$capture)) {
            list(, $variable, $agg, $agghint, $type, $hint) = $capture;
            return array('variable'=>strtolower($variable), 'aggregate'=>($agg?:null), 'aggregateHint'=>($agg?$agghint:null), 'type'=>$type, 'hint'=>$hint);
        }

        return array('variable'=>strtolower($field));
    }

    /**
     * Checks if the given variable is avialable.
     */
    public function has($var) {
        return isset($this->vars[$var]) && $this->vars[$var] != array();
    }

    public function hasField($field) {
        // parse field
        $field = $this->parseField($field);
        $var = $field['variable'];

        return $this->has($var);
    }

    protected function fetchField($field, $default=null) {
        $var = $field['variable'];

        // check availability, and fix default value if necessary
        $values = $this->has($var) ? $this->vars[$var] : ($default==null?array():array($default));

        // load any defined aggregation
        $aggregate = $this->types->loadAggregate($field['aggregate']);
        $aggregateHint = $field['aggregateHint'];

        // execute aggregator
        $values = $aggregate->aggregate($values, $aggregateHint);

        return $values;
    }

    public function getField($mode, &$R, $field, $default=null) {
        // parse field
        $field = $this->parseField($field);

        // gracefully handle unavailable fields
        return join(', ',$this->fetchField($field, $default));
    }

    public function displayField($mode, &$R, $field, $default=null) {
        // parse field
        $field = $this->parseField($field);

        $values = $this->fetchField($field, $default);

        // did the field have type info?
        $var = $field['variable'];
        if(isset($field['type'])) {
            $type = $this->types->loadType($field['type']);
            $typeName = $field['type'];
            $hint = $field['hint'];
        } else {
            // try the typemap
            if(isset($this->typemap[$var])) {
                $type = $this->typemap[$var]['type'];
                $typeName = $this->typemap[$var]['typeName'];
                $hint = $this->typemap[$var]['hint'];
            } else {
                // use the configured default type
                list($type,$hint) = $this->types->getDefaultType();
                $typeName = $type;
                $type = $this->types->loadType($type);
            }
        }

        // display fields
        if($values != array()) {
            $firstvalue = true;
            if($mode == 'xhtml') $R->doc .= '<span class="strata_field">';
            foreach($values as $value) {
                if(!$firstvalue) $R->doc .= ', ';
                if($mode == 'xhtml') $R->doc .= '<span class="strata_value stratatype_'.$typeName.'">';
                $type->render($mode, $R, $this->triples, $value, $hint);
                if($mode == 'xhtml') $R->doc .= '</span>';
                $firstvalue = false;
            }
            if($mode == 'xhtml') $R->doc .= '</span>';
        }

        return true;
    }
}

