<?php
/**
 *
 */

namespace App\Services;


use Illuminate\Database\Eloquent\ModelNotFoundException;

class ConfigData
{
    /**
     * @var array
     */
    protected $_config;

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function getConfig(string $key, $default = null)
    {
        $config = $this->_loadConfig();
        return $config[$key] ?? $default;
    }

    /**
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function setConfig(string $key, string $value)
    {
        try {
            // Try to load based on the key
            $configData = \App\Models\ConfigData::where('path', '=', $key)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            $configData = new \App\Models\ConfigData();
        }

        // Set key and value
        $configData->path = $key;
        $configData->value = $value;
        $configData->save();

        if ($this->_isConfigLoaded()) {
            $this->_config[$key] = $value;
        }

        return $this;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasConfig(string $key)
    {
        $config = $this->_loadConfig();

        return isset($config[$key]);
    }

    /**
     * @return array
     */
    protected function _loadConfig()
    {
        if (!$this->_isConfigLoaded()) {
            $this->_config = [];
            $configData = \App\Models\ConfigData::all();
            /** @var \App\Models\ConfigData $configDatum */
            foreach($configData as $configDatum) {
                $this->_config[$configDatum->path] = $configDatum->value;
            }
        }

        return $this->_config;
    }

    /**
     * @return bool
     */
    protected function _isConfigLoaded()
    {
        return !is_null($this->_config);
    }
}