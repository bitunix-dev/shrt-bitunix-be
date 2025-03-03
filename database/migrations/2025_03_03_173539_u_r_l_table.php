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
        Schema::create('urls', function (Blueprint $table) {
            $table->id();
            $table->text('destination_url');
            $table->text('short_link'); // Batasi panjang short link
            $table->longText('qr_code')->nullable();
            $table->string('source')->nullable()->index();
            $table->string('medium')->nullable()->index();
            $table->string('campaign')->nullable()->index();
            $table->string('term')->nullable()->index();
            $table->string('content')->nullable()->index();
            $table->string('referral')->nullable()->index();
            $table->integer('clicks')->default(0);
            $table->timestamps();
        });        
    }

    public function down()
    {
        Schema::dropIfExists('urls');
    }
};