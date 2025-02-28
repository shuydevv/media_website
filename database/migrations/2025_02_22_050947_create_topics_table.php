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
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('section_id')->nullable(); // foreign key
            $table->timestamps();

            $table->index('section_id', 'topic_section_idx');
            $table->foreign('section_id', 'topic_section_fk')->on('sections')->references('id');
   
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
