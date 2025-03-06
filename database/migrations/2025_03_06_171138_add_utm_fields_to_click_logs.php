<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('click_logs', function (Blueprint $table) {
            $table->string('source')->nullable();
            $table->string('medium')->nullable();
            $table->string('campaign')->nullable();
            $table->string('term')->nullable();
            $table->string('content')->nullable();
        });
    }

    public function down()
    {
        Schema::table('click_logs', function (Blueprint $table) {
            $table->dropColumn(['source', 'medium', 'campaign', 'term', 'content']);
        });
    }
};