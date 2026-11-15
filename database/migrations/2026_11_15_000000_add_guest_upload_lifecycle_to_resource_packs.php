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
            $table->string('upload_type', 16)->default('registered')->after('user_id');
            $table->timestamp('guest_expires_at')->nullable()->after('current_version_id');
            $table->string('guest_token_hash', 64)->nullable()->after('guest_expires_at');
            $table->string('guest_ip_hash', 64)->nullable()->after('guest_token_hash');
            $table->unsignedInteger('delete_attempts')->default(0)->after('guest_ip_hash');
            $table->text('delete_error')->nullable()->after('delete_attempts');
            $table->timestamp('delete_attempted_at')->nullable()->after('delete_error');

            $table->index('upload_type', 'rp_upload_type_index');
            $table->index('guest_expires_at', 'rp_guest_expires_index');
            $table->index(['upload_type', 'status', 'guest_expires_at'], 'rp_guest_status_expires_index');
            $table->unique('guest_token_hash', 'rp_guest_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('resource_packs', function (Blueprint $table) {
            $table->dropUnique('rp_guest_token_unique');
            $table->dropIndex('rp_guest_status_expires_index');
            $table->dropIndex('rp_guest_expires_index');
            $table->dropIndex('rp_upload_type_index');
            $table->dropColumn([
                'delete_attempted_at',
                'delete_error',
                'delete_attempts',
                'guest_ip_hash',
                'guest_token_hash',
                'guest_expires_at',
                'upload_type',
            ]);
            $table->foreignId('user_id')->change();
        });
    }
};
