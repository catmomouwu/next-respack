<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_packs', function (Blueprint $table) {
            if (! Schema::hasColumn('resource_packs', 'upload_type')) {
                $table->string('upload_type', 16)->default('registered')->after('user_id');
            }

            if (! Schema::hasColumn('resource_packs', 'guest_expires_at')) {
                $table->timestamp('guest_expires_at')->nullable()->after('current_version_id');
            }

            if (! Schema::hasColumn('resource_packs', 'guest_token_hash')) {
                $table->string('guest_token_hash', 64)->nullable()->after('guest_expires_at');
            }

            if (! Schema::hasColumn('resource_packs', 'guest_ip_hash')) {
                $table->string('guest_ip_hash', 64)->nullable()->after('guest_token_hash');
            }

            if (! Schema::hasColumn('resource_packs', 'delete_attempts')) {
                $table->unsignedInteger('delete_attempts')->default(0)->after('guest_ip_hash');
            }

            if (! Schema::hasColumn('resource_packs', 'delete_error')) {
                $table->text('delete_error')->nullable()->after('delete_attempts');
            }

            if (! Schema::hasColumn('resource_packs', 'delete_attempted_at')) {
                $table->timestamp('delete_attempted_at')->nullable()->after('delete_error');
            }
        });

        try {
            Schema::table('resource_packs', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->change();
            });
        } catch (Throwable) {
            // The column is already nullable on upgraded installations.
        }

        $indexes = [
            'rp_upload_type_index' => ['upload_type'],
            'rp_guest_expires_index' => ['guest_expires_at'],
            'rp_guest_status_expires_index' => ['upload_type', 'status', 'guest_expires_at'],
        ];

        foreach ($indexes as $name => $columns) {
            try {
                Schema::table('resource_packs', function (Blueprint $table) use ($name, $columns) {
                    $table->index($columns, $name);
                });
            } catch (Throwable) {
                // Index already exists (for example after an in-place upgrade).
            }
        }

        try {
            Schema::table('resource_packs', function (Blueprint $table) {
                $table->unique('guest_token_hash', 'rp_guest_token_unique');
            });
        } catch (Throwable) {
            // Unique index already exists.
        }

        if (Schema::hasColumn('resource_packs', 'upload_type')) {
            DB::table('resource_packs')
                ->whereNull('user_id')
                ->whereNotNull('guest_expires_at')
                ->update(['upload_type' => 'guest']);
        }
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
