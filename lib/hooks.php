<?php

if (!defined('ABSPATH'))
    die('No direct access allowed');

//07-11-2024
class Botoscope_Hooks {

    private static $actions = array();

    public static function apply_action($hook, $default = null, $args = []) {
        if (isset(self::$actions[$hook]) AND !empty(self::$actions[$hook])) {

            if (empty($args)) {
                $args = [$default];
            }

            $res = is_array($default) ? $default : [];

            if (!empty(self::$actions[$hook])) {
                foreach (self::$actions[$hook] as $f) {
                    $tmp = $f(...$args);

                    if (is_array($tmp)) {
                        $res = array_merge($res, $tmp);
                    } else {
                        $res = array_merge($res, [$tmp]);
                    }
                }
            }

            return $res;
        }

        return $default;
    }

    public static function add_action($hook, $action) {
        if (!isset(self::$actions[$hook])) {
            self::$actions[$hook] = [];
        }

        array_push(self::$actions[$hook], $action);
    }
}
