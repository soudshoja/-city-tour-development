<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TaskFlightDetail;
use App\Models\Airport;
use App\Models\Airline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateFlightDetailsToForeignKeys extends Command
{
    protected $signature = 'migrate:flight-details-fk {--dry-run : Run without saving changes}';
    protected $description = 'Migrate flight details from string to foreign keys';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('Running in DRY RUN mode - no changes will be saved');
        }

        $this->info('Starting migration...');

        $flights = TaskFlightDetail::all();
        $total = $flights->count();
        $bar = $this->output->createProgressBar($total);

        $stats = [
            'total' => $total,
            'airport_from_mapped' => 0,
            'airport_to_mapped' => 0,
            'airline_mapped' => 0,
            'airport_from_missing' => [],
            'airport_to_missing' => [],
            'airline_missing' => [],
        ];

        if (!$isDryRun) {
            DB::beginTransaction();
        }

        try {
            foreach ($flights as $flight) {
                if ($flight->airport_from) {
                    $airport = $this->findAirport($flight->airport_from);
                    if ($airport) {
                        $flight->airport_from_id = $airport->id;
                        $stats['airport_from_mapped']++;
                    } else {
                        $stats['airport_from_missing'][] = $flight->airport_from;
                    }
                }

                if ($flight->airport_to) {
                    $airport = $this->findAirport($flight->airport_to);
                    if ($airport) {
                        $flight->airport_to_id = $airport->id;
                        $stats['airport_to_mapped']++;
                    } else {
                        $stats['airport_to_missing'][] = $flight->airport_to;
                    }
                }

                if ($flight->airline_id || $flight->flight_number) {
                    $airline = $this->findAirline($flight->airline_id, $flight->flight_number);
                    if ($airline) {
                        $flight->airline_id_new = $airline->id;
                        $stats['airline_mapped']++;
                    } else {
                        $stats['airline_missing'][] = $flight->airline_id;
                    }
                }

                if (!$isDryRun) {
                    $flight->save();
                }
                $bar->advance();
            }

            if (!$isDryRun) {
                DB::commit();
            }

            $bar->finish();
            $this->newLine(2);

            $this->info('Migration completed!');
            $this->newLine();
            $this->table(
                ['Metric', 'Count', 'Percentage'],
                [
                    ['Total Records', $stats['total'], '100%'],
                    ['Airport From Mapped', $stats['airport_from_mapped'], round($stats['airport_from_mapped'] / $stats['total'] * 100, 2) . '%'],
                    ['Airport To Mapped', $stats['airport_to_mapped'], round($stats['airport_to_mapped'] / $stats['total'] * 100, 2) . '%'],
                    ['Airline Mapped', $stats['airline_mapped'], round($stats['airline_mapped'] / $stats['total'] * 100, 2) . '%'],
                ]
            );

            if (count($stats['airport_from_missing']) > 0) {
                $this->newLine();
                $this->warn('Airports FROM not found (' . count($stats['airport_from_missing']) . ' unique):');
                $this->line(implode(', ', array_unique($stats['airport_from_missing'])));
            }

            if (count($stats['airport_to_missing']) > 0) {
                $this->newLine();
                $this->warn('Airports TO not found (' . count($stats['airport_to_missing']) . ' unique):');
                $this->line(implode(', ', array_unique($stats['airport_to_missing'])));
            }

            if (count($stats['airline_missing']) > 0) {
                $this->newLine();
                $this->warn('Airlines not found (' . count($stats['airline_missing']) . ' unique):');
                $this->line(implode(', ', array_unique($stats['airline_missing'])));
            }

        } catch (\Exception $e) {
            if (!$isDryRun) {
                DB::rollBack();
            }
            $this->error('Migration failed: ' . $e->getMessage());
            Log::error('Flight details migration failed', ['error' => $e->getMessage()]);
            return 1;
        }

        return 0;
    }

    private function findAirport($airportString)
    {
        if (!$airportString) {
            return null;
        }

        $search = trim($airportString);

        $airport = Airport::where('iata_code', strtoupper($search))->first();
        if ($airport) {
            return $airport;
        }

        $airport = Airport::whereRaw('UPPER(name) LIKE ?', ['%' . strtoupper($search) . '%'])->first();
        if ($airport) {
            return $airport;
        }

        $airport = Airport::whereRaw('UPPER(name_ar) LIKE ?', ['%' . strtoupper($search) . '%'])->first();
        if ($airport) {
            return $airport;
        }

        return null;
    }

    private function findAirline($airlineName, $flightNumber)
    {
        if ($flightNumber) {
            $iataCode = preg_replace('/[^A-Z]/', '', strtoupper($flightNumber));
            if (strlen($iataCode) >= 2) {
                $iataCode = substr($iataCode, 0, 2);
                $airline = Airline::where('iata_designator', $iataCode)->first();
                if ($airline) {
                    return $airline;
                }
            }
        }

        if ($airlineName) {
            $search = strtoupper(trim($airlineName));

            $airline = Airline::whereRaw('UPPER(name) LIKE ?', ['%' . $search . '%'])->first();
            if ($airline) {
                return $airline;
            }

            $airline = Airline::whereRaw('UPPER(name_ar) LIKE ?', ['%' . $search . '%'])->first();
            if ($airline) {
                return $airline;
            }
        }

        return null;
    }
}
