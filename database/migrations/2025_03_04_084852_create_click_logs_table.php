<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('click_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id')->constrained()->onDelete('cascade');
            $table->string('ip_address');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['url_id', 'ip_address']); // Hindari duplikasi IP di URL yang sama
        });
    }

    public function down()
    {
        Schema::dropIfExists('click_logs');
    }
};