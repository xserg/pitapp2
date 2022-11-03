<?php namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;

/**
 * Class EnvironmentType
 * @package App\Models\Project
 * @property string $name
 */
class EnvironmentType extends Model
{

    protected $table = 'environment_types';
    protected $guarded = ['id'];

    const ID_COMPUTE = 1;
    const ID_CONVERGED = 2;
    const ID_CLOUD = 3;
    const ID_COMPUTE_STORAGE = 4;

    /**
     * @param $type
     * @return \Illuminate\Database\Eloquent\Collection|Model
     */
    public static function findByType($type)
    {
        return self::findOrFail($type);
    }
}
