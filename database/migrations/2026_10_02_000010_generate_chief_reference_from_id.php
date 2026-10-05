<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenerateChiefReferenceFromId extends Migration
{
    public function up()
    {
        $lastReferenceNumber = (int) DB::table('center_chiefs')->max('id');

        DB::table('center_chiefs')
            ->whereNull('chief_reference')
            ->orWhere('chief_reference', '')
            ->orderBy('id')
            ->get()
            ->each(function ($chief) {
                DB::table('center_chiefs')->where('id', $chief->id)->update([
                    'chief_reference' => sprintf('PRCC-%05d', $chief->id),
                    'updated_at' => now(),
                ]);
            });

        Schema::create('center_chief_reference_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('last_value');
        });

        DB::table('center_chief_reference_sequences')->insert([
            'id' => 1,
            'last_value' => $lastReferenceNumber,
        ]);

        Schema::table('center_chiefs', function (Blueprint $table) {
            $table->unique('chief_reference', 'center_chiefs_chief_reference_unique');
        });

        DB::unprepared('CREATE TRIGGER center_chiefs_before_insert_reference
            BEFORE INSERT ON center_chiefs
            FOR EACH ROW
            BEGIN
                IF NEW.chief_reference IS NULL OR NEW.chief_reference = \'\' THEN
                    UPDATE center_chief_reference_sequences
                    SET last_value = LAST_INSERT_ID(last_value + 1)
                    WHERE id = 1;
                    SET NEW.chief_reference = CONCAT(\'PRCC-\', LPAD(LAST_INSERT_ID(), 5, \'0\'));
                END IF;
            END');
    }

    public function down()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS center_chiefs_before_insert_reference');
        Schema::table('center_chiefs', function (Blueprint $table) {
            $table->dropUnique('center_chiefs_chief_reference_unique');
        });
        Schema::dropIfExists('center_chief_reference_sequences');
    }
}
