<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('artist_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();
            
            $table->string('studio_name')->nullable();
            $table->text('bio')->nullable();

            $table->string('phone')->nullable();
            $table->string('instagram')->nullable();

            $table->string('address')->nullable();
            $table->string('city');
            $table->string('state');

            // Geolocalização
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artist_profiles');
    }
};
