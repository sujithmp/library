<?php

namespace DivineOmega\ArrayUndot;


class ArrayHelpers
{
    public $attributes = [
        'customer_dc_info.*.dc_code',
        'customer_dc_info.*.city',
        'customer_dc_info.*.country',
        'customer_dc_info.*.state',
    ];
    /**
     * Expands a dot notation array into a full multi-dimensional array
     *
     * @param array $dotNotationArray
     * @return array
     */
    public function undot(array $dotNotationArray)
    {
        $array = [];
        foreach ($dotNotationArray as $key => $value) {
            // if($key == 'customer_dc_info'){
            //     dd($dotNotationArray,$key);
            // }
            $this->set($array, $key, $value);
        }

        return $array;
    }

    /**
     * Set an array item to a given value using "dot" notation.
     *
     * If no key is given to the method, the entire array will be replaced.
     *
     * @param  array   $array
     * @param  string  $key
     * @param  mixed   $value
     * @return array
     */
    public function set(&$array, $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }
        $keys = explode('.', $key);
        $clone = [];

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if(is_numeric($key))  {
                array_push($clone,$key);
                continue;
            }
            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (! isset($array[$key]) || ! is_array($array[$key])) {
                $array[$key] = [];
            }
            $array = &$array[$key];
        }
        if(count($clone) > 0) {
            $value[0] = str_replace(':row', array_reverse($clone)[0], $value[0]);
        }
        $array[array_shift($keys)][] = $value[0];
        return $array;
    }

    /**
     * Flatten a multi-dimensional associative array with dots.
     *
     * @param  array   $array
     * @param  string  $prepend
     * @return array
     */
    public static function dot($array, $prepend = '')
    {
        $results = [];
        foreach ($array as $key => $value) {
            if (is_array($value) && ! empty($value)) {
                $results = array_merge($results, static::dot($value, $prepend.$key.'.'));
            } else {
                $results[$prepend.$key] = $value;
            }
        }
        return $results;
    }
}
