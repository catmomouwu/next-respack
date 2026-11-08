<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_packs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->timestamp('guest_expires_at')->nullable()->after('current_version_id');
            $table->index('guest_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('resource_packs', function (Blueprint $table) {
            $table->dropIndex(['guest_expires_at']);
            $table->dropColumn('guest_expires_at');
        });
    }
};
