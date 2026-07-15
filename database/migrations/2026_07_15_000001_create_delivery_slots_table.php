<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('slot_number')->unique();
            $table->time('time_start');
            $table->time('time_end');
            $table->string('label')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('delivery_slots')->insert([
            ['slot_number' => 1, 'time_start' => '07:30:00', 'time_end' => '09:30:00', 'label' => 'Pagi 1', 'created_at' => $now, 'updated_at' => $now],
            ['slot_number' => 2, 'time_start' => '09:30:00', 'time_end' => '11:30:00', 'label' => 'Pagi 2', 'created_at' => $now, 'updated_at' => $now],
            ['slot_number' => 3, 'time_start' => '11:30:00', 'time_end' => '13:30:00', 'label' => 'Siang 1', 'created_at' => $now, 'updated_at' => $now],
            ['slot_number' => 4, 'time_start' => '13:30:00', 'time_end' => '15:30:00', 'label' => 'Siang 2', 'created_at' => $now, 'updated_at' => $now],
            ['slot_number' => 5, 'time_start' => '15:30:00', 'time_end' => '17:30:00', 'label' => 'Sore 1', 'created_at' => $now, 'updated_at' => $now],
            ['slot_number' => 6, 'time_start' => '17:30:00', 'time_end' => '19:30:00', 'label' => 'Sore 2', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_slots');
    }
};
