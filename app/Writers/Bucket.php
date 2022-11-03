<?php namespace App\Writers;

class Bucket {
    
    /**
     * The parent bucket
     */
    protected $bucket;
    
    /**
     * The name of this bucket
     */
    protected $name;

    public function __construct($name, $bucket = null) {
        
        $this->name = $name;
        
        $this->bucket = $bucket; 
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getParent() {
        return $this->bucket;
    }
    
    public function hasParent() {
        return $this->bucket !== null;
    }
    
    public function getBucketPath() {
        
        if($this->hasParent()) {
            return $this->getParent()->getBucketPath() . "/" . $this->name;
        } else {
            return $this->name;
        }
        
    }

}
