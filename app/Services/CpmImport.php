<?php
/**
 *
 */

namespace App\Services;


use App\Models\Hardware\Manufacturer;
use App\Models\Hardware\Processor;
use App\Models\Hardware\Server;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CpmImport
{
    /**
     * Give a human-friendly code to each line of the spreadsheet
     * @var array
     */
    protected $_columnMap = [
        'processor_is_default' => 0,
        'manufacturer_name' => 1,
        'model_name' => 2,
        'processor_name' => 3,
        'processor_announced_date' => 4,
        'processor_architecture' => 5,
        'processor_ghz' => 6,
        'processor_socket_qty' => 7,
        'processor_core_qty' => 8,
        'processor_total_cores' => 9,
        'processor_rpm' => 10
    ];

    /**
     * @throws \Throwable
     */
    public function import($path)
    {
        Log::info("Importing from $path");
        ini_set("max_execution_time", 9000);
        ini_set("memory_limit", "2048M");

        \DB::beginTransaction();

        /** @var Collection $columDefs */
        $columDefs = collect($this->_columnMap);

        try {
            if (($handle = fopen($path, "r")) === FALSE) {
                throw new \Exception("No file found");
            }

            // Get the headers
            $headers = fgetcsv($handle);

            Log::info("Got headers: " . implode(",", $headers));

            // Delete all entries from the relational table
            \DB::statement("TRUNCATE server_processors");

            Log::info("truncated server_processors table");

            // Force system to recalculate all environments
            \DB::statement("UPDATE environments set is_dirty = 1");

            Log::info("updated environments as dirty");

            /*
             * Goals of import:
             *   1. Import each row of CSV into processors table as-is
             *   2. A "default" version of every imported processor is imported as well
             *   3. Group processors by manufacturer/model_name and import/update server that points to those processors.
             *   4. Ensure processors no longer used are deleted
             */

            $importedByHash = [];
            $importedById = [];
            $defaultCandidatesByHash = [];
            $processorsByServerHash = [];
            while (($data = fgetcsv($handle)) !== FALSE) {
                $rowData = $columDefs->map(function($columnIndex, $columnKey) use ($data){
                    return trim($data[$columnIndex]);
                });

                // Read from CSV into processor data fields
                $processorData = $this->toProcessorData($rowData);

                // Create/Update based on processor hash data (finds existing and update or create)
                $processor = $this->createOrUpdate($processorData);

                //  After Create/Update, add to importedById and importedByHash
                $processorHash = $this->toProcessorHash($processorData);
                $importedById[$processor->id] = $processor;
                $importedByHash[$processorHash] = $processor;

                // Add processor to server processor array if model_name set
                if (strlen($processor->model_name)) {
                    // Copy processorData and unset model_name and get hash
                    $defaultProcessorData = $processorData;
                    $defaultProcessorData["model_name"] = "";
                    $defaultProcessorData["is_default"] = 1;
                    $defaultHash = $this->toProcessorHash($defaultProcessorData);

                    $serverHash = $this->toServerHash($processorData);
                    if (!array_key_exists($serverHash, $processorsByServerHash)) {
                        $processorsByServerHash[$serverHash] = [];
                    }
                    array_push($processorsByServerHash[$serverHash], $defaultHash);

                    // If a default hasn't already been imported, import as default if set or add as a default candidate
                    if (!array_key_exists($defaultHash, $importedByHash)) {
                        if (strlen($rowData->get("processor_is_default"))) {
                            //  After Create/Update, add to importedById and importedByHash
                            $defaultProcessorData["is_default"] = 1;
                            $defaultProcessor = $this->createOrUpdate($defaultProcessorData);
                            $importedById[$defaultProcessor->id] = $defaultProcessor;
                            $importedByHash[$defaultHash] = $defaultProcessor;

                            // Delete default candidate if set because it's now been imported
                            if (array_key_exists($defaultHash, $defaultCandidatesByHash)) {
                                unset($defaultCandidatesByHash[$defaultHash]);
                            }
                        } else {
                            if (!array_key_exists($defaultHash, $defaultCandidatesByHash) 
                                || $defaultCandidatesByHash[$defaultHash]["rpm"] < $defaultProcessorData["rpm"]) {
                                $defaultCandidatesByHash[$defaultHash] = $defaultProcessorData;
                            }
                        }
                    }
                } else if (array_key_exists($processorHash, $defaultCandidatesByHash)) {
                    // Delete default candidate if set because it's now been imported
                    unset($defaultCandidatesByHash[$processorHash]);
                }
            }

            echo sprintf("%d default candidates\n", count($defaultCandidatesByHash));
            // For each set of processors that are candidates for default, pick one with largest CPM
            foreach ($defaultCandidatesByHash as $defaultHash => $defaultProcessorData) {
                //  After Create/Update, add to importedById and importedByHash
                $defaultProcessor = $this->createOrUpdate($defaultProcessorData);
                $importedById[$defaultProcessor->id] = $defaultProcessor;
                $importedByHash[$defaultHash] = $defaultProcessor;
            }

            // For each server, create/update and set processors
            foreach ($processorsByServerHash as $serverHash => $processorHashes) {
                $keyParts = explode("::", $serverHash);
                // Map processor hashes to the processor
                $processors = array_map(function($processorHash) use (&$importedByHash) {
                    return $importedByHash[$processorHash];
                }, $processorHashes);
                $server = Server::firstOrCreate([
                    "manufacturer_id" => (int) $keyParts[0],
                    "name" => $keyParts[1],
                ]);
                // Map processors to just ids
                $server->processors()->sync(array_map(function($processor) use (&$processors) {
                    return $processor->id;
                }, $processors));
            }

            Log::info("Imported all data from CSV");

            $toDelete = [];
            // Chunk all the processors and find those needing deleting
            \DB::table('processors')->orderBy('id')->chunk(250, function($rows) use ($importedById, &$toDelete) {
                foreach($rows as $processor) {
                    if (!array_key_exists($processor->id, $importedById)) {
                        array_push($toDelete, $processor->id);
                    }
                }
            });

            Log::info("Deleting " . count($toDelete) . " processors...");
            foreach($toDelete as $id) {
                // We can't delete inside the chunk()
                // as it messes up the limit/offset calculation
                // laravel makes when you first call the function

                // Delete optimal target first
                \DB::table('optimal_targets')
                    ->where('processor_id', $id)
                    ->delete();
                // Delete processor
                \DB::table('processors')->delete($id);
            }
            Log::info("Processors deleted!");

            \DB::statement("DELETE FROM server_configurations WHERE environment_id IS NULL AND processor_id IS NULL and IFNULL(is_converged,0) = 0");

            Log::info("Deleted server_configurations where environment_id and processor_id is NULL");

            \DB::commit();

            Log::info("Committed transaction!");

            $this->_removeCacheFiles();

            return true;
        } catch (\Throwable $e) {
            if (\DB::transactionLevel() > 0) {
                \DB::rollBack();
            }

            throw $e;
        }
    }

    public function toProcessorData(Collection $rowData): array
    {
        return [
            "name" => trim($rowData->get("processor_name")),
            "architecture" => trim($rowData->get("processor_architecture")),
            "ghz" => $rowData->get("processor_ghz") ?: 0,
            "socket_qty" => $rowData->get("processor_socket_qty") ?: 0,
            "core_qty" => $rowData->get("processor_core_qty") ?: 0,
            "model_name" => $rowData->get("model_name"),
            "manufacturer_id" => Manufacturer::firstOrCreate(array(
                "name" => $rowData->get("manufacturer_name")
            ))->id,
            "is_default" => strlen($rowData->get("processor_is_default")) ? 1 : 0,
            "announced_date" =>  CpmImport::parseDate($rowData->get("processor_announced_date")),
            "rpm" => $rowData->get("processor_rpm") ?: 0
        ];
    }

    public function toProcessorHashData($data): array {
        return [
            "manufacturer_id" => $data["manufacturer_id"],
            "name" => $data["name"],
            "architecture" => $data["architecture"],
            "ghz" => $data["ghz"],
            "socket_qty" => $data["socket_qty"],
            "core_qty" => $data["core_qty"],
            "model_name" => $data["model_name"]
        ];
    }

    public function toProcessorHash($data): string {
        return implode("::", $this->toProcessorHashData($data));
    }

    public function toServerHash(array $data): string {
        return implode("::", [
            $data["manufacturer_id"],
            $data["model_name"]
        ]);
    }

    public function createOrUpdate($processorData): Processor {
        $processor = Processor::firstOrCreate($this->toProcessorHashData($processorData));
        // Set rest fields and save
        $processor->update($processorData);
        return $processor;
    }

    /**
     * @return $this
     */
    protected function _removeCacheFiles()
    {
        $serverCache = public_path().'/core/hardware_cache/servers.json';
        $processorCache = public_path().'/core/hardware_cache/processors.json';
        $manufacturersCache = public_path().'/core/hardware_cache/manufacturers.json';

        if (file_exists($serverCache)) {
            unlink($serverCache);
        }

        if (file_exists($processorCache)) {
            unlink($processorCache);
        }

        if (file_exists($manufacturersCache)) {
            unlink($manufacturersCache);
        }

        return $this;
    }

    /**
     * @param $date
     * @return string
     */
    private static function parseDate($date)
    {
        // Test if the date in an excel 5 digits format then do format to the UNIX timestamp
        if (preg_match('/^\d{5}$/', $date) > 0) {
            $date = ($date - 25569) * 86400;
        }

        try {
            $carbonDate = new Carbon($date);

            return $carbonDate->toDateString();
        } catch (\Exception $ex) {
            return null;
        }
    }
}
