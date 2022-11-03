<?php

namespace App\Models\Project\Cloud;

use App\Models\Project\Provider;
use Illuminate\Database\Eloquent\Model;

/**
 * A cloud provider's(Azure, AWS,...) Payment Option
 */
class PaymentOption extends Model
{
    const TERMTYPE_RESERVED = 'Reserved';
    const TERMTYPE_PAY_AS_YOU_GO = 'Pay as you go';

    const AZURE_PAY_AS_YOU_GO = 'Pay As You Go';
    const AZURE_PAY_AS_YOU_GO_AHB = 'Pay As You Go With Azure Hybrid Benefit';
    const AZURE_ONE_YEAR_RESERVED = '1 Year Reserved';
    const AZURE_ONE_YEAR_RESERVED_AHB = '1 Year Reserved With Azure Hybrid Benefit';
    const AZURE_THREE_YEAR_RESERVED = '3 Year Reserved';
    const AZURE_THREE_YEAR_RESERVED_AHB = '3 Year Reserved With Azure Hybrid Benefit';

    protected $table = 'payment_options';
    
    protected $fillable = [
        'name',
        'term_type',
        'lease_contract_length',
        'is_hybrid',
        /** @var array OS/Softwares that come with the payment option */
        'available_os_softwares',
        'provider_id',
        'provider_service_type',
    ];
    
    protected $attributes = [
        'available_os_softwares' => '[]',
    ];
    
    protected $casts = [
        'available_os_softwares' => 'array',
    ];

    /**
     * The Payment Option's Provider
     */
    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }
}
