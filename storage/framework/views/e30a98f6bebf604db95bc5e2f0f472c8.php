<?php $__env->startSection('title', __('Storage')); ?>
<?php $__env->startSection('heading', __('Storage')); ?>
<?php $__env->startSection('content'); ?>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="nr-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?php echo e(__('Active backend')); ?></p>
            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-slate-100"><?php echo e($driver === 's3' ? 'S3' : __('Local')); ?></p>
        </div>
        <div class="nr-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?php echo e(__('Files')); ?></p>
            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-slate-100"><?php echo e(number_format($usage['files'])); ?></p>
        </div>
        <div class="nr-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?php echo e(__('Stored bytes')); ?></p>
            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-slate-100"><?php echo e(nr_bytes($usage['bytes'])); ?></p>
        </div>
        <div class="nr-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?php echo e(__('Free disk space')); ?></p>
            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-slate-100"><?php echo e($localFree !== null ? nr_bytes((int) $localFree) : '—'); ?></p>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <div class="nr-card p-5" x-data="{ driver: '<?php echo e(old('target_driver', $settings['driver'] === 'local' ? 's3' : 'local')); ?>', testing: false, result: null }">
            <p class="nr-section-title"><?php echo e(__('Storage configuration & migration')); ?></p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?php echo e(__('Existing versions keep their current backend until a migration copies and verifies every file.')); ?></p>

            <form method="POST" action="<?php echo e(route('admin.storage.plan')); ?>" id="storage-plan-form" class="mt-4 space-y-3">
                <?php echo csrf_field(); ?>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-700" :class="driver === 'local' ? 'border-emerald-500' : ''">
                        <input type="radio" name="target_driver" value="local" x-model="driver" class="text-emerald-600">
                        <?php echo e(__('Local path')); ?>

                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-700" :class="driver === 's3' ? 'border-emerald-500' : ''">
                        <input type="radio" name="target_driver" value="s3" x-model="driver" class="text-emerald-600">
                        S3
                    </label>
                </div>

                <div x-show="driver === 'local'">
                    <label class="nr-label"><?php echo e(__('Local directory')); ?></label>
                    <input class="nr-input font-mono text-xs" name="local_path" value="<?php echo e($settings['local_path']); ?>">
                    <p class="nr-help"><?php echo e(__('Saved as storage_local_path after migration. Must be writable and outside the public web root.')); ?></p>
                </div>

                <div x-show="driver === 's3'" x-cloak class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="nr-label"><?php echo e(__('Endpoint')); ?></label>
                        <input class="nr-input" name="s3_endpoint" value="<?php echo e($settings['s3_endpoint']); ?>" placeholder="https://s3.example.com">
                    </div>
                    <div>
                        <label class="nr-label"><?php echo e(__('Region')); ?></label>
                        <input class="nr-input" name="s3_region" value="<?php echo e($settings['s3_region']); ?>">
                    </div>
                    <div>
                        <label class="nr-label"><?php echo e(__('Bucket')); ?></label>
                        <input class="nr-input" name="s3_bucket" value="<?php echo e($settings['s3_bucket']); ?>">
                    </div>
                    <div>
                        <label class="nr-label"><?php echo e(__('Access key')); ?></label>
                        <input class="nr-input" name="s3_access_key" value="<?php echo e($settings['s3_access_key']); ?>" autocomplete="off">
                    </div>
                    <div>
                        <label class="nr-label"><?php echo e(__('Secret key')); ?></label>
                        <input class="nr-input" name="s3_secret_key" type="password" value="" placeholder="<?php echo e($settings['secret_set'] ? __('Leave blank to keep the saved secret') : ''); ?>" autocomplete="new-password">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="nr-label"><?php echo e(__('Path prefix')); ?></label>
                        <input class="nr-input" name="s3_prefix" value="<?php echo e($settings['s3_prefix']); ?>">
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="nr-btn-secondary" :disabled="testing"
                            @click="
                                testing = true; result = null;
                                const form = document.getElementById('storage-plan-form');
                                fetch('<?php echo e(route('admin.storage.test')); ?>', {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>', Accept: 'application/json' },
                                    body: new FormData(form),
                                }).then(async (response) => {
                                    const data = await response.json().catch(() => ({ ok: false, errors: ['<?php echo e(__('Unexpected response')); ?>'] }));
                                    result = data;
                                }).catch((error) => { result = { ok: false, errors: [error.message] }; })
                                .finally(() => { testing = false; });
                            ">
                        <span x-show="!testing"><?php echo e(__('Test configuration')); ?></span>
                        <span x-show="testing" x-cloak><?php echo e(__('Testing…')); ?></span>
                    </button>
                    <button type="submit" class="nr-btn-primary"><?php echo e(__('Create migration plan')); ?></button>
                </div>

                <div class="space-y-2" x-show="result !== null" x-cloak>
                    <div class="nr-success" x-show="result?.ok"><?php echo e(__('The target storage is writable and readable.')); ?></div>
                    <div class="nr-error" x-show="result && !result.ok">
                        <template x-for="error in (result?.errors || [])" :key="error"><p x-text="error"></p></template>
                    </div>
                </div>
            </form>
        </div>

        <div class="nr-card p-5">
            <p class="nr-section-title"><?php echo e(__('Maintenance')); ?></p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?php echo e(__('Clean local files that do not belong to any database record. A file is only removed after the version record is truly gone (including soft-deleted versions).')); ?></p>
            <form method="POST" action="<?php echo e(route('admin.storage.orphans')); ?>" class="mt-4" onsubmit="return confirm('<?php echo e(__('Scan and delete orphan local files? Keep a backup first.')); ?>')">
                <?php echo csrf_field(); ?>
                <button class="nr-btn-secondary" type="submit" <?php if($driver !== 'local'): echo 'disabled'; endif; ?>><?php echo e(__('Clean orphan files')); ?></button>
            </form>
            <?php if($driver !== 'local'): ?>
                <p class="nr-warning mt-3 text-xs"><?php echo e(__('Orphan cleanup is implemented for local storage only.')); ?></p>
            <?php endif; ?>

            <div class="mt-6 border-t border-slate-100 pt-4 dark:border-slate-800">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?php echo e(__('Current local path')); ?></p>
                <p class="mt-1 break-all font-mono text-xs text-slate-500 dark:text-slate-400"><?php echo e($settings['local_path']); ?></p>
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400"><?php echo e(__('Local bytes: :local · S3 bytes: :s3', ['local' => nr_bytes($usage['local_bytes']), 's3' => nr_bytes($usage['s3_bytes'])])); ?></p>
            </div>
        </div>
    </div>

    <div class="nr-card mt-4 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <p class="nr-section-title !text-base"><?php echo e(__('Migration history')); ?></p>
        </div>
        <div class="overflow-x-auto">
            <table class="nr-table">
                <thead>
                    <tr>
                        <th class="nr-th"><?php echo e(__('From')); ?></th>
                        <th class="nr-th"><?php echo e(__('To')); ?></th>
                        <th class="nr-th"><?php echo e(__('Status')); ?></th>
                        <th class="nr-th"><?php echo e(__('Files')); ?></th>
                        <th class="nr-th"><?php echo e(__('Started')); ?></th>
                        <th class="nr-th"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $migrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $migration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="nr-td"><?php echo e($migration->from_driver); ?></td>
                            <td class="nr-td"><?php echo e($migration->to_driver); ?></td>
                            <td class="nr-td">
                                <span class="nr-badge <?php echo e(in_array($migration->status, ['completed']) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : (in_array($migration->status, ['failed']) ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300')); ?>"><?php echo e($migration->status); ?></span>
                                <?php if($migration->error): ?>
                                    <p class="mt-1 max-w-xs text-[11px] text-red-600 dark:text-red-400"><?php echo e($migration->error); ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="nr-td text-xs"><?php echo e($migration->stats['copied_files'] ?? ($migration->stats['files'] ?? '—')); ?></td>
                            <td class="nr-td text-xs text-slate-500"><?php echo e($migration->started_at?->format('Y-m-d H:i') ?? $migration->created_at?->format('Y-m-d H:i')); ?></td>
                            <td class="nr-td text-right">
                                <?php if(in_array($migration->status, ['planned', 'failed'])): ?>
                                    <form method="POST" action="<?php echo e(route('admin.storage.run', $migration)); ?>" onsubmit="return confirm('<?php echo e(__('Run this migration now? Large datasets can take a long time.')); ?>')">
                                        <?php echo csrf_field(); ?>
                                        <button class="nr-btn-primary !px-2.5 !py-1.5 text-xs" type="submit"><?php echo e($migration->status === 'failed' ? __('Retry') : __('Run migration')); ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="6" class="nr-td py-8 text-center text-sm text-slate-500"><?php echo e(__('No migrations yet.')); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/next-respack/resources/views/admin/storage/index.blade.php ENDPATH**/ ?>