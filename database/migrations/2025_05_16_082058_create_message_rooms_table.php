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
        Schema::create('message_rooms', function (Blueprint $table) {
            $table->id();
            $table->enum('room_type', ['private', 'group']);
            $table->string('room_name')->nullable(); // 그룹명 (단체톡만 사용)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_rooms');
    }
};
