<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('click_logs', function (Blueprint $table) {
            $table->string('country')->nullable()->after('ip_address');
            $table->string('city')->nullable()->after('country');
            $table->string('region')->nullable()->after('city');
            $table->string('continent')->nullable()->after('region');
            $table->string('device')->nullable()->after('continent');
            $table->string('browser')->nullable()->after('device');
        });
    }

    public function down()
    {
        Schema::table('click_logs', function (Blueprint $table) {
            $table->dropColumn(['country', 'city', 'region', 'continent', 'device', 'browser']);
        });
    }
};