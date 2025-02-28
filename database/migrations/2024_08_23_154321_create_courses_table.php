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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('description');
            $table->string('main_image')->nullable();
            $table->text('content')->nullable();
            $table->string('path')->nullable();
            $table->string('html_title')->nullable();
            $table->string('html_description')->nullable();
            $table->string('price');
            $table->string('old_price')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();

            $table->index('category_id', 'category_course_idx');
            $table->foreign('category_id', 'category_course_fk')->on('categories')->references('id');
            
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
