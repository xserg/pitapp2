<?php

namespace App\Models\Hardware;

use Illuminate\Database\Eloquent\Model;

class IBMPVSStorage extends Model
{
    const STORAGE_TYPES = [
        ['id' => 1, 'name' => 'Tier 1'],
        ['id' => 2, 'name' => 'Tier 2']
    ];

    protected $table = 'ibmpvs_storages';
    protected $guarded = ['id'];

    /**
     * Get the storage type by id
     * @param integer $id
     * @return array
     */
    static public function getIBMPVSStorageTypeById($id)
    {
        foreach (self::STORAGE_TYPES as $storage) {
            if ($storage['id'] === $id) return $storage;
        }
    }
}
