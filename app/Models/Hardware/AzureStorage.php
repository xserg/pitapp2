<?php

namespace App\Models\Hardware;

use Illuminate\Database\Eloquent\Model;

class AzureStorage extends Model
{
    const STORAGE_TYPES = [
        ['id' => 1, 'name' => 'Premium SSD Managed Disks'],
        ['id' => 2, 'name' => 'Ultra SSD Managed Disks'],
        ['id' => 3, 'name' => 'Standard SSD Managed Disks'],
        ['id' => 4, 'name' => 'Standard HDD Managed Disks']
    ];

    protected $table = 'azure_storages';
    protected $guarded = ['id'];

    /**
     * Get the storage type by id
     * @param integer $id
     * @return array
     */
    static public function getAzureStorageTypeById($id)
    {
        foreach (self::STORAGE_TYPES as $storage) {
            if ($storage['id'] === $id) return $storage;
        }
    }
}
