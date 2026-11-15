<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_pack_versions', function (Blueprint $table) {
            $table->string('recommended_game_version', 64)->nullable()->after('detected_versions');
            $table->string('recommended_version_type', 24)->nullable()->after('recommended_game_version');
        });

        Schema::table('minecraft_pack_formats', function (Blueprint $table) {
            $table->string('min_format', 32)->nullable()->after('format_value');
            $table->string('max_format', 32)->nullable()->after('min_format');
            $table->text('supported_formats')->nullable()->after('max_format');
        });
    }

    public function down(): void
    {
        Schema::table('minecraft_pack_formats', function (Blueprint $table) {
            $table->dropColumn(['min_format', 'max_format', 'supported_formats']);
        });

        Schema::table('resource_pack_versions', function (Blueprint $table) {
            $table->dropColumn(['recommended_game_version', 'recommended_version_type']);
        });
    }
};
