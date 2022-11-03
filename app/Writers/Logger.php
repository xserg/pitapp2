<?php namespace App\Writers;

class Logger {
    
    protected $bucket;
    
    protected $path;

    public function __construct($bucket) {
        $this->bucket = $bucket;
        
        $this->path = env('LOG_FILE', base_path() . "/log/") . $this->bucket->getBucketPath();
        if(!file_exists($this->path)) {
            mkdir($this->path, 0775, true);

            // Addition swheeler 2015-12-09 for [499]. Try to maintain consistent file permissions.
            // chown($this->path, @fileowner(dirname($this->path)));
            // chgrp($this->path, @filegroup(dirname($this->path)));
        }
    }
    
    public function log($message) {
        $filename = $this->path . '/' . date('Y-m-d') . ".log";
        $log = fopen($filename, 'a+');
        fwrite($log, date('Y-m-d H:i:s') . ' ' . $message . "\n");
        fclose($log);
        
        // Addition swheeler 2015-12-09 for [499]. Try to maintain consistent file permissions.
        // chmod($filename, intval(substr(decoct(@fileperms($this->path)), -3), 8));
        // chown($filename, @fileowner($this->path));
        // chgrp($filename, @filegroup($this->path));
    }

}
