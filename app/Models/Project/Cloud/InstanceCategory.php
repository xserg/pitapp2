<?php

namespace App\Models\Project\Cloud;

use Illuminate\Database\Eloquent\Model;

/**
 * A cloud provider's Instance Category/Fammilly
 */
class InstanceCategory extends Model
{
    /** 
     * @var string Groups all "*Optimized" categories under a single name
     */
    const CATEGORY_SYSTEM_OPTIMIZED = 'System Optimized';

    const CATEGORY_MEMORY_OPTIMIZED = 'Memory Optimized';

    const CATEGORY_COMPUTE_OPTIMIZED = 'Compute Optimized';

    const CATEGORY_GENERAL_PURPOSE = 'General Purpose';
    
    /** @var array List of instance categories contained in "System Optimized" */
    const SYSTEM_OPTIMIZED_CATEGORIES = [
        self::CATEGORY_GENERAL_PURPOSE,
        self::CATEGORY_COMPUTE_OPTIMIZED,
        self::CATEGORY_MEMORY_OPTIMIZED,
    ];

    protected $table = 'instance_categories';

    protected $fillable = [
        'name',
        'provider_id',
        'provider_service_type',
    ];
    
    protected $attributes = [
        /** @var array PaymentOption names available for this region */
        'available_payment_options' => '[]',
    ];
    
    protected $casts = [
        'available_payment_options' => 'array',
    ];
    
    /**
     * The Instance Category's Provider
     */
    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }
}
