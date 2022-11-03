<?php

namespace App\Models\Software;

use Illuminate\Database\Eloquent\Model;

/**
 * Class SoftwareType
 * @package App\Models\Software
 * @property string $name
 */
class SoftwareType extends Model
{
    const NAME_DATABASE = 'Database';
    const NAME_MIDDLEWARE = 'Middleware';

    protected $table = 'software_types';
    protected $guarded = ['id'];

    /**
     * @return string
     */
    public function isDatabase()
    {
        return $this->name == self::NAME_DATABASE;
    }

    /**
     * @return bool
     */
    public function isMiddleware()
    {
        return $this->name == self::NAME_MIDDLEWARE;
    }

    /**
     * @return bool
     */
    public function isDatabaseOrMiddleware()
    {
        return $this->isDatabase() || $this->isMiddleware();
    }
}