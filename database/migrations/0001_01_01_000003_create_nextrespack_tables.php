<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installations', function (Blueprint $table) {
            $table->id();
            $table->string('version', 32);
            $table->timestamp('installed_at');
            $table->foreignId('admin_user_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->foreign('admin_user_id', 'installations_admin_user_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('user_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->unsignedInteger('pack_count')->default(0);
            $table->timestamps();
            $table->foreign('user_id', 'user_quotas_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->unique('user_id', 'user_quotas_user_unique');
        });

        Schema::create('resource_packs', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 64);
            $table->foreignId('user_id');
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('user_id', 'rp_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->unique('public_id', 'rp_public_id_unique');
            $table->index('status', 'rp_status_index');
            $table->index(['user_id', 'created_at'], 'rp_user_created_index');
            $table->index(['user_id', 'status'], 'rp_user_status_index');
        });

        Schema::create('resource_pack_versions', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 64);
            $table->foreignId('resource_pack_id');
            $table->unsignedInteger('version_number')->default(1);
            $table->string('original_filename', 255);
            $table->string('stored_filename', 191);
            $table->string('storage_driver', 24)->default('local');
            $table->string('storage_path', 255);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('sha1', 40)->nullable();
            $table->string('token_hash', 64)->nullable();
            $table->unsignedInteger('token_generation')->default(1);
            $table->boolean('download_enabled')->default(true);
            $table->unsignedBigInteger('downloads_count')->default(0);
            $table->string('validation_status', 32)->default('VALID');
            $table->json('validation_details')->nullable();
            $table->string('format_value', 32)->nullable();
            $table->unsignedInteger('format_major')->nullable();
            $table->unsignedInteger('format_minor')->nullable();
            $table->boolean('format_unknown')->default(false);
            $table->json('detected_versions')->nullable();
            $table->longText('pack_mcmeta_raw')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('resource_pack_id', 'rpv_pack_fk')->references('id')->on('resource_packs')->cascadeOnDelete();
            $table->unique('public_id', 'rpv_public_id_unique');
            $table->unique('token_hash', 'rpv_token_unique');
            $table->index('sha1', 'rpv_sha1_index');
            $table->index('download_enabled', 'rpv_download_enabled_index');
            $table->index('validation_status', 'rpv_validation_index');
            $table->index('format_value', 'rpv_format_index');
            $table->index(['resource_pack_id', 'created_at'], 'rpv_pack_created_index');
            $table->unique(['resource_pack_id', 'version_number'], 'rpv_pack_version_unique');
        });

        Schema::table('resource_packs', function (Blueprint $table) {
            $table->foreign('current_version_id', 'rp_current_version_fk')->references('id')->on('resource_pack_versions')->nullOnDelete();
        });

        Schema::create('download_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_pack_id');
            $table->foreignId('resource_pack_version_id');
            $table->string('method', 8)->default('GET');
            $table->unsignedSmallInteger('status_code')->default(200);
            $table->string('range_header', 191)->nullable();
            $table->unsignedBigInteger('bytes_sent')->default(0);
            $table->boolean('is_range')->default(false);
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->foreign('resource_pack_id', 'dl_logs_pack_fk')->references('id')->on('resource_packs')->cascadeOnDelete();
            $table->foreign('resource_pack_version_id', 'dl_logs_version_fk')->references('id')->on('resource_pack_versions')->cascadeOnDelete();
            $table->index(['resource_pack_id', 'created_at'], 'dl_logs_pack_date_index');
            $table->index(['resource_pack_version_id', 'created_at'], 'dl_logs_version_date_index');
            $table->index('created_at', 'dl_logs_date_index');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 191)->primary();
            $table->text('value')->nullable();
            $table->string('group', 64)->default('general');
            $table->string('type', 24)->default('string');
            $table->timestamps();
            $table->index('group', 'settings_group_index');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable();
            $table->string('action', 64);
            $table->string('subject_type', 191)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->foreign('actor_id', 'audit_actor_fk')->references('id')->on('users')->nullOnDelete();
            $table->index('action', 'audit_action_index');
            $table->index(['action', 'created_at'], 'audit_action_date_index');
        });

        Schema::create('minecraft_pack_formats', function (Blueprint $table) {
            $table->id();
            $table->string('edition', 32)->default('java_resource_pack');
            $table->string('format_key', 64);
            $table->unsignedInteger('format_major');
            $table->unsignedInteger('format_minor')->nullable();
            $table->string('format_value', 32);
            $table->string('source_url', 191)->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->string('dataset_version', 64)->nullable();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();
            $table->unique('format_key', 'mcpf_format_key_unique');
            $table->unique(['edition', 'format_major', 'format_minor'], 'mcpf_format_unique');
            $table->index('format_value', 'mcpf_format_value_index');
        });

        Schema::create('minecraft_pack_format_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minecraft_pack_format_id');
            $table->string('edition', 32)->default('java_resource_pack');
            $table->string('version_name', 191);
            $table->string('version_type', 32)->default('release');
            $table->string('version_channel', 32)->nullable();
            $table->boolean('is_snapshot')->default(false);
            $table->boolean('is_prerelease')->default(false);
            $table->boolean('is_release_candidate')->default(false);
            $table->date('release_date')->nullable();
            $table->string('source_url', 191)->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();
            $table->foreign('minecraft_pack_format_id', 'mcpf_versions_format_fk')->references('id')->on('minecraft_pack_formats')->cascadeOnDelete();
            $table->unique(['edition', 'version_name'], 'mcpf_versions_edition_version_unique');
            $table->index('version_type', 'mcpf_versions_type_index');
            $table->index('is_snapshot', 'mcpf_versions_snapshot_index');
            $table->index('is_prerelease', 'mcpf_versions_prerelease_index');
            $table->index('is_release_candidate', 'mcpf_versions_rc_index');
            $table->index(['minecraft_pack_format_id', 'version_type'], 'mcpf_versions_format_type_index');
        });

        Schema::create('minecraft_pack_format_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 24)->default('running');
            $table->string('trigger', 32)->default('manual');
            $table->string('source_url', 191)->nullable();
            $table->string('parser_version', 32)->nullable();
            $table->string('dataset_version', 64)->nullable();
            $table->unsignedInteger('records_added')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_unchanged')->default(0);
            $table->unsignedInteger('records_removed')->default(0);
            $table->json('changes')->nullable();
            $table->longText('raw_payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index('status', 'mcpf_sync_status_index');
            $table->index('created_at', 'mcpf_sync_created_index');
        });

        Schema::create('minecraft_pack_format_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_log_id')->nullable();
            $table->string('dataset_version', 64)->nullable();
            $table->longText('snapshot');
            $table->string('note', 191)->nullable();
            $table->timestamps();
            $table->foreign('sync_log_id', 'mcpf_history_sync_fk')->references('id')->on('minecraft_pack_format_sync_logs')->nullOnDelete();
        });

        Schema::create('storage_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('from_driver', 32);
            $table->string('to_driver', 32);
            $table->string('status', 24)->default('planned');
            $table->json('stats')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index('status', 'storage_migrations_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_migrations');
        Schema::dropIfExists('minecraft_pack_format_history');
        Schema::dropIfExists('minecraft_pack_format_sync_logs');
        Schema::dropIfExists('minecraft_pack_format_versions');
        Schema::dropIfExists('minecraft_pack_formats');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('download_logs');
        Schema::table('resource_packs', function (Blueprint $table) {
            $table->dropForeign('rp_current_version_fk');
        });
        Schema::dropIfExists('resource_pack_versions');
        Schema::dropIfExists('resource_packs');
        Schema::dropIfExists('user_quotas');
        Schema::dropIfExists('installations');
    }
};
