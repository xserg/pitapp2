<?php
/**
 *
 */

namespace App\Exceptions;


class AnalysisException extends \Exception
{
    /**
     * @var \stdClass
     */
    protected $_data;

    /**
     * @param array $data
     * @return $this
     */
    public function setData($data = null)
    {
        $this->_data = $data;
        return $this;
    }

    /**
     * @return \stdClass
     */
    public function getData()
    {
        return $this->_data;
    }
}