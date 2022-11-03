<?php namespace App\Models\Hardware;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AmazonServer
 * @package App\Models\Hardware
 *
 * @property int $id
 * @property string $term_type
 * @property string $term_length
 * @property string $service_type
 * @property string $database_type
 * @property string $category
 * @property string $name
 * @property int $vcpu_qty
 * @property float $ram
 * @property float $price_per_unit
 * @property string $processor
 * @property string $ghz
 * @property string $region
 * @property float $utilRam
 * @property string $ads_description
 * @property string $instance_type
 * @property null $os_id
 * @property null $middleware_id
 * @property null $hypervisor_id
 * @property null $database_id
 * @property null $os_li_name
 * @property null $os_name
 * @property null $database_li_name
 * @property null $database
 */
class AzureAds extends Model
{
    const INSTANCE_TYPE_ADS = 'ADS';
    const SERVICE_TYPE = 'Azure SQL Database(ads)';

    const TERM_TYPE_LI = 'License Included';
    const TERM_TYPE_RESERVED = 'Reserved';

    const TERM_LENGTH_PAYASYOUGO = '';
    const TERM_LENGTH_AZUREHYBRID = 'Azure Hybrid';
    const TERM_LENGTH_1YEAR = '1';
    const TERM_LENGTH_3YEAR = '3';
    const TERM_LENGTH_3YEAR_AZUREHYBRID = '3 Azure Hybrid';

    const SERVICE_TYPE_MANAGED_INSTANCE = 'Managed Instance';
    const SERVICE_TYPE_ELASTIC_POOL = 'Elastic Pool';
    const SERVICE_TYPE_SINGLE_DATABASE = 'Single Database';

    const DATABASE_TYPE_AZURESQL = 'Azure Sql Database';
    const DATABASE_TYPE_MYSQL = 'Azure Database for MySQL';
    const DATABASE_TYPE_POSTGRESQL = 'Azure Database for PostgreSQL';
    const DATABASE_TYPE_MARIADB = 'Azure Database for MariaDB';

    const CATEGORY_GENERAL_PURPOSE = 'General Purpose';
    const CATEGORY_BUSINESS_CRITICAL = 'Business Critical';
    const CATEGORY_COMPUTE = 'Compute';

    const NAME_GEN4 = 'Gen 4';
    const NAME_GEN5 = 'Gen 5';

    const REGION_US_WEST2 = 'West US 2';

    const STORAGE_COST_PER_GB_GENERAL_PURPOSE = .115;
    const STORAGE_COST_PER_GB_BUSINESS_CRITICAL = .25;
    const STORAGE_FREE_GB_MANAGED_INSTANCE = 32;

    const PAYMENT_OPTIONS = [
        ['id' => 0 , 'name' => 'Pay As You Go'],
        ['id' => 1 , 'name' => 'Pay As You Go With Azure Hybrid Benefit'],
        ['id' => 2 , 'name' => 'One Year Reserved'],
        ['id' => 3 , 'name' => 'Three Year Reserved'],
        ['id' => 4 , 'name' => '3 Year Reserved With Azure Hybrid Benefit'],
    ];

    /**
     * @var array
     */
    protected static $_uniqueFields = ['term_type', 'term_length', 'service_type', 'database_type', 'category', 'name', 'vcpu_qty', 'ram', 'max_db_per_pool', 'included_storage', 'clock_speed', 'region'];

    /**
     * @var string
     */
    protected $table = 'azure_ads';

    /**
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * @return array
     */
    public static function getUniqueFields()
    {
        return self::$_uniqueFields;
    }

    /**
     * @return string
     */
    public function isAzureHybrid()
    {
        return strstr($this->term_length, "Azure Hybrid");
    }

    /**
     * @return $this
     */
    public function setAdsDescription()
    {
        $this->ads_description = "{$this->database_type} {$this->name} ({$this->service_type} / {$this->category}" ;

        if ($this->isAzureHybrid()) {
            $this->ads_description .= " / Azure Hybrid";
        }

        $this->ads_description .= ") {$this->vcpu_qty} cores @ {$this->clock_speed} Ghz with {$this->ram} GB of RAM";
        return $this;
    }

    /**
     * @return $this
     */
    public function setAdsDefaults()
    {
        $this->setAdsDescription();
        $this->os_id = null;
        $this->middleware_id = null;
        $this->hypervisor_id = null;
        $this->database_id = null;
        $this->os_li_name = null;
        $this->os_name = null;
        $this->database_li_name = null;
        $this->database = null;

        return $this;
    }

    /**
     * @param $a
     * @param $b
     * @return bool
     */
    public static function doAzureDatabaseInstancesMatch($a, $b)
    {
        foreach(self::getUniqueFields() as $field) {
            if ($a->{$field} != $b->{$field}) {
                return false;
            }
        }

        return true;
    }
}
