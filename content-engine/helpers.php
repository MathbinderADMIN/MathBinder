<?php

if (!function_exists('mathbinder_content_engine_root_dir')) {
    function mathbinder_content_engine_root_dir()
    {
        return dirname(__DIR__);
    }
}

if (!function_exists('mathbinder_content_engine_content_dir')) {
    function mathbinder_content_engine_content_dir()
    {
        return mathbinder_content_engine_root_dir() . DIRECTORY_SEPARATOR . 'content';
    }
}

if (!function_exists('mathbinder_content_engine_engine_dir')) {
    function mathbinder_content_engine_engine_dir()
    {
        return dirname(__FILE__);
    }
}
