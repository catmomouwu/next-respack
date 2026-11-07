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
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('user_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->unsignedInteger('pack_count')->default(0);
            $table->timestamps();
        });

        Schema::create('resource_packs', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('resource_pack_versions', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 64)->unique();
            $table->foreignId('resource_pack_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('original_filename', 255);
            $table->string('stored_filename', 191);
            $table->string('storage_driver', 24)->default('local');
            $table->string('storage_path', 255);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('sha1', 40)->nullable()->index();
            $table->string('token_hash', 64)->nullable()->unique();
            $table->unsignedInteger('token_generation')->default(1);
            $table->boolean('download_enabled')->default(true)->index();
            $table->unsignedBigInteger('downloads_count')->default(0);
            $table->string('validation_status', 32)->default('VALID')->index();
            $table->json('validation_details')->nullable();
            $table->string('format_value', 32)->nullable()->index();
            $table->unsignedInteger('format_major')->nullable();
            $table->unsignedInteger('format_minor')->nullable();
            $table->boolean('format_unknown')->default(false);
            $table->json('detected_versions')->nullable();
            $table->longText('pack_mcmeta_raw')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['resource_pack_id', 'created_at']);
            $table->unique(['resource_pack_id', 'version_number']);
        });

        Schema::table('resource_packs', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('resource_pack_versions')->nullOnDelete();
        });

        Schema::create('download_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_pack_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_pack_version_id')->constrained()->cascadeOnDelete();
            $table->string('method', 8)->default('GET');
            $table->unsignedSmallInteger('status_code')->default(200);
            $table->string('range_header', 191)->nullable();
            $table->unsignedBigInteger('bytes_sent')->default(0);
            $table->boolean('is_range')->default(false);
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['resource_pack_id', 'created_at']);
            $table->index(['resource_pack_version_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 191)->primary();
            $table->text('value')->nullable();
            $table->string('group', 64)->default('general')->index();
            $table->string('type', 24)->default('string');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64)->index();
            $table->string('subject_type', 191)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['action', 'created_at']);
        });

        Schema::create('minecraft_pack_formats', function (Blueprint $table) {
            $table->id();
            $table->string('edition', 32)->default('java_resource_pack');
            $table->string('format_key', 64)->unique();
            $table->unsignedInteger('format_major');
            $table->unsignedInteger('format_minor')->nullable();
            $table->string('format_value', 32);
            $table->string('source_url', 191)->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->string('dataset_version', 64)->nullable();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();
            $table->unique(['edition', 'format_major', 'format_minor'], 'minecraft_pack_formats_unique');
            $table->index('format_value');
        });

        Schema::create('minecraft_pack_format_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minecraft_pack_format_id')->constrained('minecraft_pack_formats')->cascadeOnDelete();
            $table->string('edition', 32)->default('java_resource_pack');
            $table->string('version_name', 191);
            $table->string('version_type', 32)->default('release')->index();
            $table->string('version_channel', 32)->nullable();
            $table->boolean('is_snapshot')->default(false)->index();
            $table->boolean('is_prerelease')->default(false)->index();
            $table->boolean('is_release_candidate')->default(false)->index();
            $table->date('release_date')->nullable();
            $table->string('source_url', 191)->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();
            $table->unique(['edition', 'version_name']);
            $table->index(['minecraft_pack_format_id', 'version_type']);
        });

        Schema::create('minecraft_pack_format_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 24)->default('running')->index();
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
            $table->index('created_at');
        });

        Schema::create('minecraft_pack_format_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_log_id')->nullable()->constrained('minecraft_pack_format_sync_logs')->nullOnDelete();
            $table->string('dataset_version', 64)->nullable();
            $table->longText('snapshot');
            $table->string('note', 191)->nullable();
            $table->timestamps();
        });

        Schema::create('storage_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('from_driver', 32);
            $table->string('to_driver', 32);
            $table->string('status', 24)->default('planned')->index();
            $table->json('stats')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
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
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('resource_pack_versions');
        Schema::dropIfExists('resource_packs');
        Schema::dropIfExists('user_quotas');
        Schema::dropIfExists('installations');
    }
};
