<?php

namespace App\Models\Hardware;

use Illuminate\Database\Eloquent\Model;

class AmazonStorage extends Model
{
    const STORAGE_TYPES = [
        ['id' => 2, 'name' => 'EBS General Purpose SSD', 'type' => 'ebs'],
        ['id' => 3, 'name' => 'EBS Provisioned IOPS SSD', 'type' => 'ebs'],
        ['id' => 4, 'name' => 'EBS Throughput Optimized HDD', 'type' => 'ebs'],
        ['id' => 5, 'name' => 'EBS Cold HDD', 'type' => 'ebs'],
        ['id' => 1, 'name' => 'EBS to Snapshots to Amazon S3', 'type' => 'ebs'],
        ['id' => 6, 'name' => 'RDS General Purpose (SSD)', 'type' => 'rds', 'volume_type' => 'General Purpose'],
        ['id' => 7, 'name' => 'RDS Provisioned IOPS (SSD)', 'type' => 'rds', 'volume_type' => 'Provisioned IOPS'],
        ['id' => 8, 'name' => 'RDS Magnetic', 'type' => 'rds', 'volume_type' => 'Magnetic'],
        ['id' => 9, 'name' => 'Aurora Database Storage and IOs', 'type' => 'rds', 'volume_type' => 'General Purpose-Aurora']
    ];

    protected $table = 'amazon_storages';
    protected $guarded = ['id'];

    /**
     * Get the storage type by id
     * @param integer $id
     * @return array
     */
    static public function getAmazonStorageTypeById($id)
    {
        foreach (self::STORAGE_TYPES as $storage) {
            if ($storage['id'] === $id) return $storage;
        }
    }
}
