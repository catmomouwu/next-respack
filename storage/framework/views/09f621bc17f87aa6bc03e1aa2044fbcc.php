<?php $__env->startSection('title', __('Welcome to Next Respack')); ?>
<?php $__env->startSection('heading', __('Welcome to Next Respack')); ?>
<?php $__env->startSection('subheading', __('A complete self-hosted platform for Minecraft Java Edition resource packs.')); ?>
<?php $__env->startSection('steps'); ?>
    <?php echo $__env->make('components.install-steps', ['current' => 0], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="grid gap-8 lg:grid-cols-[1.35fr_1fr]">
        <div>
            <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                <?php echo e(__('Next Respack helps Minecraft Java Edition server administrators upload, validate, host and share resource packs. It generates anonymous HTTPS download links, verifies resource pack metadata against the official pack format history, and produces ready-to-paste server.properties lines.')); ?>

            </p>

            <ul class="mt-6 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                <?php $__currentLoopData = [
                    __('Upload ZIP files with strict integrity and security validation.'),
                    __('Automatic SHA-1 calculation on the final stored file.'),
                    __('Automatic Minecraft pack format detection and version mapping.'),
                    __('Anonymous, revocable download links with HTTP Range support.'),
                    __('Local or S3-compatible storage, managed from the admin panel.'),
                    __('Works on shared hosting without SSH, Composer or Cron.'),
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feature): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="flex gap-2.5">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                            <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3"><path d="m5 12 4.5 4.5L19 7"/></svg>
                        </span>
                        <?php echo e($feature); ?>

                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>

            <?php if(! empty($state['status'])): ?>
                <div class="nr-warning mt-6">
                    <p class="font-semibold"><?php echo e(__('Previous installation attempt')); ?>: <?php echo e($state['status']); ?></p>
                    <?php if(! empty($state['step'])): ?>
                        <p class="mt-1"><?php echo e(__('Failed at step: :step', ['step' => $state['step']])); ?></p>
                    <?php endif; ?>
                    <?php if(! empty($state['message'])): ?>
                        <p class="mt-1 font-mono text-xs"><?php echo e($state['message']); ?></p>
                    <?php endif; ?>
                    <p class="mt-2 text-xs"><?php echo e(__('You can retry the wizard. Existing valid configuration is preserved.')); ?></p>
                </div>
            <?php endif; ?>

            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="<?php echo e(route('install.requirements')); ?>" class="nr-btn-primary !px-6 !py-3">
                    <?php echo e(__('Start installation')); ?>

                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-5-5 5 5-5 5"/></svg>
                </a>
                <a href="<?php echo e(route('locale', app()->getLocale() === 'zh_CN' ? 'en' : 'zh_CN')); ?>" class="nr-btn-secondary"><?php echo e(__('Switch language')); ?></a>
            </div>
        </div>

        <aside class="space-y-4">
            <div class="nr-card-muted p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?php echo e(__('Installation status')); ?></p>
                <p class="mt-2 text-sm font-semibold text-emerald-700 dark:text-emerald-400"><?php echo e(__('Not installed')); ?></p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?php echo e(__('The installer is enabled until the installation lock is written.')); ?></p>
            </div>

            <div class="nr-card-muted p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?php echo e(__('System requirements')); ?></p>
                <ul class="mt-2 space-y-1.5 text-xs text-slate-600 dark:text-slate-300">
                    <li>PHP 8.3+ · MySQL 8.0+ / MariaDB 10.6+ / SQLite</li>
                    <li><?php echo e(__('PHP extensions')); ?>: ctype, curl, dom, fileinfo, hash, mbstring, openssl, pdo, pdo_mysql, tokenizer, xml, zip</li>
                    <li>HTTPS <?php echo e(__('strongly recommended')); ?></li>
                    <li><?php echo e(__('Writable')); ?>: storage/, bootstrap/cache/, .env</li>
                </ul>
            </div>

            <div class="nr-card-muted p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?php echo e(__('Product')); ?></p>
                <p class="mt-2 font-mono text-sm text-slate-700 dark:text-slate-200">Next Respack v<?php echo e(config('nextrespack.version')); ?></p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Laravel <?php echo e(\Illuminate\Foundation\Application::VERSION); ?></p>
            </div>
        </aside>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.install', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/next-respack/resources/views/install/welcome.blade.php ENDPATH**/ ?>