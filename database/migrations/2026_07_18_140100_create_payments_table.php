<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_user_id')->constrained('course_user')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('RUB');
            $table->string('method'); // manual | promised | promo | будущий слаг шлюза
            $table->string('status')->default('succeeded'); // succeeded | pending | failed

            $table->boolean('is_promise')->default(false);
            $table->timestamp('promise_expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['course_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
