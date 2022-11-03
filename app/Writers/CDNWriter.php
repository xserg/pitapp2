<?php

namespace App\Writers;
use App\Writers\Bucket;
use App\Writers\Logger;

/**
 * CDNWriter, a writer type object tasked with handling writing to the buckets
 * from which the CDN wil read from as an origin to serve.
 * 
 * @author swheeler, hnguyen
 * @date 2015-07-31
 */
class CDNWriter {
    protected $cdnFolder;
    /** The path inside of the CDN bucket that will be written to. */
    //protected $path = null;
    /** The sites to which we are writing. */
    protected $sites = null;
    
    protected $logger;
    /**
     * Class constructor for CDNWriter. Sets up to where files shall be written.
     * @param  string [$sites=null] Array of strings - domain names - to.
     * @return void
     * @throws WritersBlock        When invalid input is given.
     */
    public function __construct($sites=null) {
        $this->cdnFolder = base_path() . "/storage/CDN";
        $this->logger = new Logger(new Bucket('standard', new Bucket('foundation')));
        $this->logger->log('Inside constructor for CDNWriter');
        if (is_null($sites)) {
            $this->logger->log("sites is null");
            $this->sites = $this->findSmartStackFolders($this->cdnFolder);
        }
        else if ($this->isArrayOfStrings($sites)) {
            $this->logger->log("Sites: " . json_encode($sites, JSON_PRETTY_PRINT));
            
            //make sure the sites branch off the cdnPath
            foreach($sites as &$site) {
                $site = $this->cdnFolder . '/' . $site;
            }
            // TODO: Add validation here
            $this->sites = $sites;
        }
        else {
            throw new WritersBlock('$sites parameter must be null or an array of strings');
        }
    }
    
    /*
     * Takes a site and two folder locations. Copies the data from the site the cdn writer
     * was constructed for and places it in the corresponding location on the new site
     * @param $site This is the site the data is being copied to
     * @param $srcFolder this is the path to the folder that is to be copied
     * @param $destFolder this is the path to the folder that is to be overwritten/created
     */
    public function copyContents($site, $srcFolder, $destFolder){
        $src = $this->sites[0].'/'.$srcFolder;
        if(file_exists($src)){
            $dst = base_path() . "/storage/CDN".'/'.$site.'/'.$destFolder;
            $this->recurse_copy($src, $dst);
        }
    }
    
    private function recurse_copy($src,$dst) {
        $dir = opendir($src);
        if(!file_exists($dst)){
            mkdir($dst, 0777, true);
        }
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src . '/' . $file) ) {
                    $this->recurse_copy($src . '/' . $file,$dst . '/' . $file);
                }
                else {
                    copy($src . '/' . $file,$dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
    
    /**
     * Takes in data and a filename and outputs that data to the location
     * specified, overwritting an old file without warning.
     * @param  string  $data         Information to be added to the file.
     * @param  string  $destination  The destination to put file. Should be a path.
     * @param  string  $filename     The name of file. Should not be a path.
     * @param  boolean [$gzip=false] Set this to true if the file should be gzipped.
     *                               Will automatically add the .gz extension to $filename
     * @return boolean               True if the write was successful.
     * @throws WritersBlock          TODO
     */
    public function write($data, $destination, $filename, $gzip=false) {
        
        //Gzip and add a file extension if requested.
        if($gzip) {
            $data = gzencode($data);
            $filename .= ".gz";
        }
        
        $this->logger->log("write  destination: $destination");
        $this->logger->log("write  filename: $filename");
        
        
        foreach($this->sites as $site) {
            //Check that we can write to this directory, and skip if we can't
            if($this->confirmDestination($site, $destination)) {
                $dir = $this->getDestinationPath($site, $destination);
                $fullPath = $dir . "/{$filename}";

                $this->logger->log("writing to $fullPath");
                $result = file_put_contents($fullPath, $data);

                if (!$result) {
                    return false;
                }
                
                // Addition swheeler 2015-12-09 for [499]. Try to maintain consistent file permissions.
                // chmod($fullPath, intval(substr(decoct(@fileperms($dir)), -3), 8));
                // chown($fullPath, @fileowner($dir));
                // chgrp($fullPath, @filegroup($dir));
            } else {
                $this->logger->log("A client failed to update. Please check the logs and permissions to ensure that all of your clients are receiving updates.");
            }
        }
        
        return true;
    }
    
    /**
     * Verifies that the absolute path exists for all sites an destinations
     * @return boolean True if the path exists, false if we were unable 
     */
    protected function confirmDestination($site, $dest) {
        
        $dir = $this->getDestinationPath($site, $dest);
        
        //Make sure the path is a directory, and that we have permission to write to it.
        if(is_dir($dir)) {
            $perms = substr(sprintf('%o', fileperms($dir)), -4);
            return $perms === '0775' || $perms === '0755' || $perms === '0644';
        }
        
        //Fail if the requested destination is a file
        if(is_file($dir)) {
            $this->logger->log("Attempting to write to a file $dir. Skipping.");
            return false;
        }
        
        //Neither a directory, nor a file, so it must not exist
        //Try to make the directory, but return false if we can't
        try {
            $dirMade = mkdir($dir, 0775, true);
        
            // Addition swheeler 2015-12-09 for [499]. Try to maintain consistent file permissions.
            // chown($dir, @fileowner(dirname($dir)));
            // chgrp($dir, @filegroup(dirname($dir)));
            
            return $dirMade;
        } catch(\Exception $e) {
            $this->logger->log("Failed to find or make directory $dir. Skipping. See below for more details.");
            $this->logger->log($e->getMessage());
            return false;
        }
    }
    
    /**
     * Constructs the absolute path of the destination folder.
     * @return string The absolute path being written to. This is a directory.
     */
    protected function getDestinationPath($site, $dest) {
        //If both have a slash, remove on of them
        if(substr($site, -1) === '/' && substr($dest, 0, 1) === '/') {
            return $site . substr($dest, 1);
        }
        //If only one has a slash, concat them normally
        if(substr($site, -1) === '/' || substr($dest, 0, 1) === '/') {
            return $site . $dest;
        }
        //Otherwise, neither has a slash. Add one
        return $site . '/' . $dest;
    }
    /**
     * 
     * @param type $arr array to check for strings
     * @return boolean returns true if array is null or has strings
     */
    private function isArrayOfStrings($arr) {
        $this->logger->log("isArrayOfStrings");
        if (is_array($arr)) {
            foreach ($arr as $item) {
                if (!is_string($item)) {
                    return false;
                }
            }
        }
        return true;
    }
    
    /**
     * Crawl down the $path and look for folders containing a vendor folder. This is assumed to be a SmartStack folder.
     * If a SmartStack folder is found, it will not crawl down its folders.
     * @param string $path
     * return array $results passed by reference, array to append folders to
     * @return array
     */
    private function findSmartStackFolders($path): array {
        $results = [];
        $this->logger->log("Looking for smartstack projects in $path");
        
        //If this directory is a smartstack client project, add it to the list
        if (is_dir($path . "/vendor") && is_dir($path . "/vendor/smartstack-c")) {
            $this->logger->log("Found smartstack client at $path");
            $results[] = $path;
        } else {
            //Create an array of sites, and recursively search the directory tree looking for smartstack
            //clients, and merge them into the sites array
            foreach (scandir($path, SCANDIR_SORT_NONE) as $file) {
                if ($file !== "." && $file !== ".." && is_dir($path . "/" . $file)) {
                    $results[] = $this->findSmartStackFolders($path . "/" . $file);
                }
            }
        }

        return $results;
    }
    
}