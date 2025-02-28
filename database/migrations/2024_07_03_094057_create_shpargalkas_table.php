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
        Schema::create('shpargalkas', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('title');
            $table->string('price');
            $table->text('description')->nullable();
            $table->text('content')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('main_image')->nullable();
            $table->string('path')->nullable();
            $table->string('html_title')->nullable();
            $table->string('html_description')->nullable();

            $table->index('category_id', 'category_shpargalka_idx');
            $table->foreign('category_id', 'category_shpargalka_fk')->on('categories')->references('id');
            $table->softDeletes();
        });

        // Schema::dropIfExists('shpargalkas');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shpargalkas');
    }
};
