<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('artist_images', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0);
        });

        // Updating the position of the images
        $artistIds = DB::table('artist_images')
            ->distinct()
            ->pluck('artist_profile_id');

        foreach ($artistIds as $artistId) {
            $imagesIds = DB::table('artist_images')
                ->where('artist_profile_id', $artistId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id');

            foreach ($imagesIds as $position => $imageId) {
                DB::table('artist_images')
                    ->where('id', $imageId)
                    ->update(['position' => $position]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artist_images', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
