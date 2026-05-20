<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('task_id')->constrained('notification_tasks')->onDelete('cascade');
            $table->foreignId('recipient_id')->constrained('users')->onDelete('cascade');
            $table->tinyInteger('status')->default(0); // 0 = pending, 1 = sent, 2 = delivered, 3 = error, 4 = delivery_failed
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempt')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('delivery_failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->timestamps();

            $table->index('recipient_id', 'idx_notifications_recipient');
            $table->index(['status', 'last_attempt'], 'idx_notifications_status_attempt');
        });

    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};
