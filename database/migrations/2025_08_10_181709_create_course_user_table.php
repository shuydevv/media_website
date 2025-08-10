<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('status', ['active','completed','dropped','suspended'])->default('active');
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->string('source')->nullable();     // 'promo' | 'payment' | 'manual'
            $table->string('payment_id')->nullable(); // внешний ID из платёжки
            $table->string('promo_code')->nullable(); // какой код использовали

            $table->timestamps();

            $table->unique(['course_id','user_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_user');
    }
};
