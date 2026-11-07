<?php $__env->startSection('title', __('Confirm installation')); ?>
<?php $__env->startSection('heading', __('Confirm installation')); ?>
<?php $__env->startSection('subheading', __('Review the configuration below. Sensitive values are masked.')); ?>

<?php $__env->startSection('steps'); ?>
    <?php echo $__env->make('components.install-steps', ['current' => 6], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <?php
        $db = $database;
        $st = $storage;
        $si = $site;
    ?>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="nr-card-muted p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?php echo e(__('Database')); ?></p>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Driver')); ?></dt><dd class="font-medium"><?php echo e(strtoupper($db['driver'] ?? '')); ?></dd></div>
                <?php if(($db['driver'] ?? '') !== 'sqlite'): ?>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Host')); ?></dt><dd class="font-mono text-xs"><?php echo e($db['host'] ?? ''); ?>:<?php echo e($db['port'] ?? ''); ?></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Database')); ?></dt><dd class="font-mono text-xs"><?php echo e($db['database'] ?? ''); ?></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Username')); ?></dt><dd class="font-mono text-xs"><?php echo e($db['username'] ?? ''); ?></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Password')); ?></dt><dd class="font-mono text-xs">••••••••</dd></div>
                <?php else: ?>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('File')); ?></dt><dd class="break-all font-mono text-xs"><?php echo e($db['sqlite_path'] ?? ''); ?></dd></div>
                <?php endif; ?>
            </dl>
        </div>

        <div class="nr-card-muted p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?php echo e(__('Storage')); ?></p>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Backend')); ?></dt><dd class="font-medium"><?php echo e($st['driver'] === 's3' ? 'S3' : __('Local')); ?></dd></div>
                <?php if(($st['driver'] ?? 'local') === 'local'): ?>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Directory')); ?></dt><dd class="break-all font-mono text-xs"><?php echo e($st['local_path'] ?? ''); ?></dd></div>
                <?php else: ?>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Bucket')); ?></dt><dd class="font-mono text-xs"><?php echo e($st['bucket'] ?? ''); ?></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Region')); ?></dt><dd class="font-mono text-xs"><?php echo e($st['region'] ?? ''); ?></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Secret key')); ?></dt><dd class="font-mono text-xs">••••••••</dd></div>
                <?php endif; ?>
            </dl>
        </div>

        <div class="nr-card-muted p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?php echo e(__('Website')); ?></p>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Name')); ?></dt><dd class="text-right font-medium"><?php echo e($si['name'] ?? ''); ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('URL')); ?></dt><dd class="break-all text-right font-mono text-xs"><?php echo e($si['url'] ?? ''); ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Language')); ?></dt><dd><?php echo e(($si['locale'] ?? 'zh_CN') === 'zh_CN' ? '简体中文' : 'English'); ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Max upload')); ?></dt><dd><?php echo e($si['max_upload_mb'] ?? ''); ?> MB</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Registration')); ?></dt><dd><?php echo e(! empty($si['allow_registration']) ? __('Open') : __('Closed')); ?></dd></div>
            </dl>
        </div>

        <div class="nr-card-muted p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?php echo e(__('Administrator')); ?></p>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Username')); ?></dt><dd class="font-medium"><?php echo e($admin['username'] ?? ''); ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Email')); ?></dt><dd class="break-all font-mono text-xs"><?php echo e($admin['email'] ?? ''); ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400"><?php echo e(__('Role')); ?></dt><dd><span class="nr-badge-green"><?php echo e(__('Super Administrator')); ?></span></dd></div>
            </dl>
        </div>
    </div>

    <div class="nr-warning mt-6">
        <?php echo e(__('The installer will run database migrations, create the administrator, initialize storage and import the bundled Minecraft pack format data. This can take a moment. Do not close this page.')); ?>

    </div>

    <form method="POST" action="<?php echo e(route('install.run')); ?>" class="mt-8" x-data="{ running: false }" @submit="running = true">
        <?php echo csrf_field(); ?>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="<?php echo e(route('install.admin')); ?>" class="nr-btn-ghost"><?php echo e(__('Back')); ?></a>
            <button type="submit" class="nr-btn-primary !px-6 !py-3" :disabled="running">
                <span x-show="!running"><?php echo e(__('Install Next Respack')); ?></span>
                <span x-show="running" x-cloak class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                    <?php echo e(__('Installing…')); ?>

                </span>
            </button>
        </div>
    </form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.install', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/next-respack/resources/views/install/confirm.blade.php ENDPATH**/ ?>