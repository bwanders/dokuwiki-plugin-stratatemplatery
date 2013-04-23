<?php
/**
 * Strata Templatery template handler
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Brend Wanders <b.wanders@utwente.nl>
 */
 
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die('Meh.');

/**
 * This templatery handler provides typed functionality.
 */
class stratatemplatery_handler implements templatery_handler {
    public function __construct($variables, &$util, &$triples, $typemap) {
        $this->syntax =& plugin_load('helper', 'strata_syntax');
        $this->vars = $variables;
        $this->util = $util;
        $this->triples = $triples;
        $this->typemap = $typemap;
    }

    /**
     * Splits a field into aggregation, type and accompanying hints.
     */
    protected function parseField($field) {
        $p = $this->syntax->getPatterns();

        if(preg_match("/^({$p->predicate})\s*({$p->aggregate})?\s*({$p->type})?$/",$field,$capture)) {
            list(, $variable, $agg, $type) = $capture;
            $variable = trim(strtolower($variable));
            list($agg, $agghint) = $p->aggregate($agg);
            list($type, $typehint) = $p->type($type);
            return array('variable'=>$variable, 'aggregate'=>$agg, 'aggregateHint'=>$agghint, 'type'=>$type, 'hint'=>$typehint);
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
        $aggregate = $this->util->loadAggregate($field['aggregate']);
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
            $type = $this->util->loadType($field['type']);
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
                list($type,$hint) = $this->util->getDefaultType();
                $typeName = $type;
                $type = $this->util->loadType($type);
            }
        }

        // display fields
        if($values != array()) {
            $this->util->renderField($mode, $R, $this->triples, $values, $typeName, $hint, $type);
        }

        return true;
    }
}

