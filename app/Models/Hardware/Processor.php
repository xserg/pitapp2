<?php
namespace App\Models\Hardware;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Models\Hardware\{Server, ServerConfiguration, Manufacturer};

/**
 * Class Processor
 * @package App\Models\Hardware
 * @property float $ghz
 * @property string $name
 * @property string $architecture
 * @property int $core_qty
 * @property int $socket_qty
 * @property bool $isAWS
 * @property float $rpm
 * @property int $id
 * @property bool $is_facade
 * @property int $total_cores
 * @property bool $is_converged
 * @property string $announced_date
 * @property string $model_name
 * @property Manufacturer $manufacturer
 */
class Processor extends Model
{
    protected $table = 'processors';
    protected $guarded = ['id'];

    /**
     * @var array
     */
    protected $_vmInfo = [];

    /**
     * @var array
     */
    protected static $_hashFields = ['name', 'core_qty', 'socket_qty', 'architecture', 'ghz', 'manufacturer_id', 'model_name'];


    public function servers() {
        return $this->belongsToMany(Server::class, 'server_processors', 'processor_id', 'server_id');
    }

    public function serverConfigurations(){
        return $this->belongsToMany(ServerConfiguration::class, 'server_configuration_processors');
    }

    public function manufacturer() {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * @param null $key
     * @return mixed|null
     */
    public function getVmInfo($key = null)
    {
        return is_null($key) ? $this->_vmInfo : $this->_vmInfo[$key] ?? null;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function setVmInfo($key, $value)
    {
        $this->_vmInfo[$key] = $value;
        return $this;
    }

    /**
     * @param $data
     * @return string
     */
    public static function getHash($data)
    {
        if (is_object($data) && $data instanceof Processor) {
            $data = $data->toArray();
        } else if (is_object($data)) {
            $data = (array) $data;
        }

        $components = [];
        foreach(self::$_hashFields as $field) {
            if (!isset($data[$field])) {
                $data[$field] = '';
            }
            // Ensure all falsey values are just empty string
            switch ($field) {
                case 'manufacturer_id':
                    if (!($data[$field] ?? false)) {
                        $manuName = '';
                    } else {
                        try {
                            $manuName = Manufacturer::firstOrFail($data[$field])->name;
                        } catch (\Throwable $e) {
                            $manuName = '';
                        }
                    }
                    $components[] = $field . ':' . $manuName;
                break;
                default:
                    $components[] = $field . ':' . ($data[$field] ?: '');
                    break;
            }
        }

        return md5(implode("|", $components));
    }

    /**
     * @param array $data
     * @return Processor|false|\Illuminate\Database\Query\Builder
     * @throws \Exception
     */
    public static function findByHashData(array $data)
    {
        if (!isset($data['model_name'])) {
            $data['model_name'] = '';
        }
        /** @var false|\Illuminate\Database\Query\Builder $query */
        $query = false;
        foreach(static::$_hashFields as $field) {
            if (!isset($data[$field])) {
                throw new \Exception("Processor hash field {$field} is missing!");
            }
            if (!$query) {
                $query = self::where($field, $data[$field]);
            } else {
                $query->where($field, $data[$field]);
            }
        }

        return $query;
    }

    /**
     * @return array
     */
    public function getHashData()
    {
        $data = [];
        foreach(self::$_hashFields as $field) {
            $data[$field] = $this->{$field};
        }
        if (!$data['model_name']) {
            $data['model_name'] = '';
        }

        return $data;
    }

    /**
     * Determine if the x86 oracle licensing model applies to this processor
     * @return bool
     */
    public function isOracleX86LicenseModel()
    {
        $name = trim($this->name);
        if (!is_object($this->manufacturer)) {
            unset($this->manufacturer);
            $this->manufacturer;
        }
        $manufacturerName = trim($this->manufacturer->name);

        if (preg_match('/^Oracle$/i', $manufacturerName)) {
            return true;
        }

        return preg_match('/^Intel$/i', $manufacturerName) && !preg_match('/Itanium/i', $name);
    }

    /**
     * Determine if the IBM oracle licensing model applies to this processor
     * @return bool
     */
    public function isIbmLicenseModel()
    {
        $name = trim($this->name);
        if (!is_object($this->manufacturer)) {
            unset($this->manufacturer);
            $this->manufacturer;
        }
        $manufacturerName = trim($this->manufacturer->name);

        if (preg_match('/^IBM$/i', $manufacturerName)) {
            return true;
        }

        return preg_match('/^Intel$/i', $manufacturerName) && preg_match('/Itanium/i', $name);
    }
}
