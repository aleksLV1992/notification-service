<?php

declare(strict_types=1);

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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('idempotency_key')->unique()->index()->comment('Ключ для дедубликации');
            $table->string('channel')->comment('Канал уведомления: sms, email');
            $table->text('message')->comment('Текст сообщения');
            $table->uuid('batch_id')->nullable()->index()->comment('ID пакетной рассылки');
            $table->string('priority')->default('normal')->index()->comment('Приоритет: critical, normal, marketing');
            $table->json('metadata')->nullable()->comment('Дополнительные метаданные');
            $table->timestamps();

            $table->index(['priority', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
