<?php

namespace App\Models\Hardware;

use Illuminate\Database\Eloquent\Model;

class GoogleStorage extends Model
{
    const STORAGE_TYPES = [
        ['id' => 1, 'name' => 'PD - Standard (HDD)'],
        ['id' => 2, 'name' => 'PD - SSD']
    ];

    protected $table = 'google_storages';
    protected $guarded = ['id'];

    /**
     * Get the storage type by id
     * @param integer $id
     * @return array
     */
    static public function getGoogleStorageTypeById($id)
    {
        foreach (self::STORAGE_TYPES as $storage) {
            if ($storage['id'] === $id) return $storage;
        }
    }
}
