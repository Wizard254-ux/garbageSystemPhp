<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Client;
use App\Models\Pickup;
use Carbon\Carbon;

class ScheduleDailyPickups extends Command
{
    protected $signature = 'pickups:schedule-daily';
    protected $description = 'Schedule daily pickups for clients based on their pickup day';

    public function handle()
    {
        \Log::info('=== DAILY PICKUP SCHEDULING STARTED ===');
        
        $today = Carbon::now();
        $todayName = strtolower($today->format('l')); // monday, tuesday, etc.
        $scheduledCount = 0;
        $skippedCount = 0;
        
        \Log::info('Scheduling pickups for:', [
            'date' => $today->toDateString(),
            'day' => $todayName
        ]);

        // Get clients whose pickup day is today
        $clients = Client::with(['user', 'route'])
            ->whereNotNull('pickUpDay')
            ->whereNotNull('serviceStartDate')
            ->get();

        \Log::info('Found clients to check:', ['count' => $clients->count()]);

        foreach ($clients as $client) {
            try {
                $clientPickupDay = strtolower($client->pickUpDay);
                $serviceStartDate = Carbon::parse($client->serviceStartDate);
                
                \Log::info('Processing client:', [
                    'client_id' => $client->id,
                    'name' => $client->user->name,
                    'pickup_day' => $clientPickupDay,
                    'service_start' => $serviceStartDate->toDateString()
                ]);

                // Check if service has started
                if ($today->lt($serviceStartDate)) {
                    \Log::info('Service not started yet for client:', ['client_id' => $client->id]);
                    $skippedCount++;
                    continue;
                }

                // Check if today matches pickup day
                if ($todayName !== $clientPickupDay) {
                    \Log::info('Not pickup day for client:', [
                        'client_id' => $client->id,
                        'expected' => $clientPickupDay,
                        'today' => $todayName
                    ]);
                    $skippedCount++;
                    continue;
                }

                // Check if pickup already exists for this week
                $weekStart = $today->copy()->startOfWeek();
                $weekEnd = $today->copy()->endOfWeek();
                
                $existingPickup = Pickup::where('client_id', $client->user_id)
                    ->whereBetween('pickup_date', [$weekStart, $weekEnd])
                    ->first();

                if ($existingPickup) {
                    \Log::info('Pickup already exists this week for client:', [
                        'client_id' => $client->id,
                        'existing_pickup_date' => $existingPickup->pickup_date,
                        'status' => $existingPickup->pickup_status
                    ]);
                    $skippedCount++;
                    continue;
                }

                // Create new pickup record
                $pickup = Pickup::create([
                    'client_id' => $client->user_id,
                    'route_id' => $client->route_id,
                    'pickup_status' => 'unpicked',
                    'pickup_date' => $today->toDateString()
                ]);

                \Log::info('Pickup scheduled:', [
                    'pickup_id' => $pickup->id,
                    'client_id' => $client->id,
                    'client_name' => $client->user->name,
                    'pickup_date' => $pickup->pickup_date
                ]);

                $scheduledCount++;

            } catch (\Exception $e) {
                \Log::error('Failed to process client pickup:', [
                    'client_id' => $client->id ?? 'unknown',
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
        }

        \Log::info('=== DAILY PICKUP SCHEDULING COMPLETED ===', [
            'scheduled_count' => $scheduledCount,
            'skipped_count' => $skippedCount,
            'total_processed' => $clients->count()
        ]);

        $this->info("=== DAILY PICKUP SCHEDULING COMPLETED ===");
        $this->info("Scheduled: {$scheduledCount} pickups");
        $this->info("Skipped: {$skippedCount} clients");

        return 0;
    }
}