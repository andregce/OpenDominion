<?php

namespace OpenDominion\Console\Commands\Game;

use DB;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use OpenDominion\Console\Commands\CommandInterface;
use OpenDominion\Models\Race;
use OpenDominion\Models\RacePerk;
use OpenDominion\Models\RacePerkType;
use OpenDominion\Models\Unit;
use OpenDominion\Models\UnitPerk;
use OpenDominion\Models\UnitPerkType;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class DataSyncCommand extends Command implements CommandInterface
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'game:data:sync';

    /** @var string The console command description. */
    protected $description = '';

    /** @var Filesystem */
    protected $filesystem;

    /**
     * DataSyncCommand constructor.
     *
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();

        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(): void
    {
        DB::transaction(function () {
            $this->syncRaces();
        });
    }

    /**
     * Syncs race, unit and perk data from .yml files to the database.
     */
    protected function syncRaces()
    {
        $files = $this->filesystem->files(base_path('app/data/races'));

        foreach ($files as $file) {
            $data = Yaml::parse($file->getContents(), Yaml::PARSE_OBJECT_FOR_MAP);

            // Race
            $race = Race::firstOrNew(['name' => $data->name])
                ->fill([
                    'alignment' => object_get($data, 'alignment'),
                    'home_land_type' => object_get($data, 'home_land_type'),
                ]);

            if (!$race->exists) {
                $this->info("Adding race {$data->name}");

            } else {
                $this->info("Processing race {$data->name}");

                $newValues = $race->getDirty();

                foreach ($newValues as $key => $newValue) {
                    $originalValue = $race->getOriginal($key);

                    $this->info("[Change] {$key}: {$originalValue} -> {$newValue}");
                }
            }

            $race->save();
            $race->refresh();

            // Race Perks
            $racePerksToSync = [];

            foreach (object_get($data, 'perks', []) as $perk => $value) {
                $value = (float)$value;

                $racePerkType = RacePerkType::firstOrCreate(['key' => $perk]);

                $racePerksToSync[$racePerkType->id] = ['value' => $value];

                $racePerk = RacePerk::query()
                    ->where('race_id', $race->id)
                    ->where('race_perk_type_id', $racePerkType->id)
                    ->first();

                if ($racePerk === null) {
                    $this->info("[Add Race Perk] {$perk}: {$value}");
                } elseif ($racePerk->value !== $value) {
                    $this->info("[Change Race Perk] {$perk}: {$racePerk->value} -> {$value}");
                }
            }

            $race->perks()->sync($racePerksToSync);

            // Units
            foreach (object_get($data, 'units', []) as $slot => $unitData) {
                $slot++; // Because array indices start at 0

                $unitName = object_get($unitData, 'name');

                $this->info("Unit {$slot}: {$unitName}", OutputInterface::VERBOSITY_VERBOSE);

                $where = [
                    'race_id' => $race->id,
                    'slot' => $slot,
                ];

                $unit = Unit::where($where)->first();

                if ($unit === null) {
                    $unit = Unit::make($where);
                }

                $unit->fill([
                    'name' => $unitName,
                    'cost_platinum' => object_get($unitData, 'cost.platinum', 0),
                    'cost_ore' => object_get($unitData, 'cost.ore', 0),
                    'power_offense' => object_get($unitData, 'power.offense', 0),
                    'power_defense' => object_get($unitData, 'power.defense', 0),
                    'need_boat' => (int)object_get($unitData, 'need_boat', true),
                ]);

                if ($unit->exists) {
                    $newValues = $unit->getDirty();

                    foreach ($newValues as $key => $newValue) {
                        $originalValue = $unit->getOriginal($key);

                        $this->info("[Change] {$key}: {$originalValue} -> {$newValue}");
                    }
                }

                $unit->save();
                $unit->refresh();

                // Unit perks
                $unitPerksToSync = [];

                foreach (object_get($unitData, 'perks', []) as $perk => $value) {
                    $value = (string)$value; // Can have multiple values for a perk, comma separated. todo: Probably needs a refactor later to JSON

                    $unitPerkType = UnitPerkType::firstOrCreate(['key' => $perk]);

                    $unitPerksToSync[$unitPerkType->id] = ['value' => $value];

                    $unitPerk = UnitPerk::query()
                        ->where('unit_id', $unit->id)
                        ->where('unit_perk_type_id', $unitPerkType->id)
                        ->first();

                    if ($unitPerk === null) {
                        $this->info("[Add Unit Perk] {$perk}: {$value}");
                    } elseif ($unitPerk->value != $value) {
                        $this->info("[Change Unit Perk] {$perk}: {$unitPerk->value} -> {$value}");
                    }
                }

                $unit->perks()->sync($unitPerksToSync);
            }
        }
    }
}
