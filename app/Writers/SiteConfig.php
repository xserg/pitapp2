<?php

namespace App\Writers;

use App\Writers\WritersBlock;

/**
 * Class for managing the .site-config file.
 * @author swheeler
 * @date 2015-07-31
 */
class SiteConfig {

    /** Singleton site config variable */
    private static $siteConfig;
    
    /**
     * Fetch a variable by name.
     * @param  string $name The name of the config variable to fetch.
     * @return string The requested value or NULL if not found.
     */
    public static function get($name) {
        self::ensureSiteConfig();
        if (isset(self::$siteConfig)) {
            return self::$siteConfig[$name];
        } else {
            return null;
        }
    }
    
    /**
     * Puts a new value into the site config.
     * @param string $name  The name of the config variable to assign to.
     * @param mixed  $value The value to be stored in that variable.
     *                      Note that regardless of type saved, it will be retrieved as a string.
     */
    public static function put($name, $value) {
        self::ensureSiteConfig();
        self::$siteConfig[$name] = $value;
        self::storeSiteConfig();
    }
    
    /**
     * Assurance method that should be called first by any function that depends
     * on the presence of the singleton $siteConfig.
     */
    protected static function ensureSiteConfig() {
        if (!isset(self::$siteConfig) && file_exists(base_path() . '/.site-config')) {
            self::$siteConfig = json_decode(file_get_contents(base_path() . '/.site-config'), true);
        }
        if (!is_array(self::$siteConfig)) {
            self::$siteConfig = array();
            self::storeSiteConfig();
        }
    }
    
    /**
     * Writes the .site-config file to disk.
     * @throws WritersBlock if unable to successfully write to disk.
     */
    protected static function storeSiteConfig() {
        self::ensureSiteConfig();
        $dir = base_path();
        $path = $dir . '/.site-config';
        $saved = file_put_contents($path, json_encode(self::$siteConfig));
        if ($saved === false) {
            throw new WritersBlock('Unable to save .site-config to disk.');
        }
        
        // Addition swheeler 2015-12-09 for [499]. Try to maintain consistent file permissions.
        // chmod($path, intval(substr(decoct(@fileperms($dir)), -3), 8));
        // chown($path, @fileowner($dir));
        // chgrp($path, @filegroup($dir));
    }
    
}