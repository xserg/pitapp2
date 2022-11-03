<?php


namespace App\Services\Consolidation\Report;


trait HasDataMapping
{
    public function getDataByPathAsMap($data = [], $map = [], $default = null)
    {
        $result = [];

        foreach ($map as $key => $path) {
            $result[$key] = is_array($path) ? $this->getDataByPath($data, $path, $default) : $path;
        }

        return $result;
    }

    public function getDataByPath($data, $path = [], $default = null)
    {
        if (count($path) < 1) {
            return isset($data) ? '' . $data : $default;
        }

        $key = array_shift($path);

        if (!isset($data->$key)) {
            return $default;
        }

        return $this->getDataByPath($data->$key, $path, $default);
    }

}