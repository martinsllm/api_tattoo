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
        Schema::create('artist_style', function (Blueprint $table) {
            $table->foreignId('artist_profile_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('style_id')
                ->constrained()
                ->cascadeOnDelete();

            // evita duplicação (mesmo estilo 2x pro mesmo artista)
            $table->primary(['artist_profile_id', 'style_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artist_style');
    }
};
