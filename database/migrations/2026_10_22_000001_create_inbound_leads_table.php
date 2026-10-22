<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * inbound_leads — заявки с других моих сайтов (InboundLeadController).
 * Как и site_leads: сначала сюда, потом в админ-группу задачей очереди,
 * чтобы недоступный Telegram не терял заявку (sent_at = null — ещё не дошла).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_leads', function (Blueprint $table) {
            $table->id();
            // slug из config/inbound_leads.php
            $table->string('site', 32);
            $table->string('name', 100);
            // «Telegram», «WhatsApp», «Звонок» — как пришло с сайта
            $table->string('method', 50)->nullable();
            $table->string('contact', 100);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_leads');
    }
};
