<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notification_tasks', function (Blueprint $table) {
            $table->id('id');
            $table->string('channel', 20);
            $table->text('message');
            $table->integer('priority')->default(5);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('notification_tasks');
    }
};
