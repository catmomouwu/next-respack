<?php $__env->startSection('title', __('Overview')); ?>
<?php $__env->startSection('heading', __('Overview')); ?>
<?php $__env->startSection('content'); ?>
    <?php ($maxDaily = max(1, collect($chart)->max('total'))); ?>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php $__currentLoopData = [
            ['label' => __('Users'), 'value' => number_format($stats['users']), 'hint' => __('registered accounts')],
            ['label' => __('Active packs'), 'value' => number_format($stats['packs']), 'hint' => __('published resource packs')],
            ['label' => __('Downloads'), 'value' => number_format($stats['downloads']), 'hint' => __(':count in the last 30 days', ['count' => number_format($stats['downloads_30d'])])],
            ['label' => __('Private storage'), 'value' => nr_bytes($stats['storage']), 'hint' => __('across all versions')],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="nr-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?php echo e($card['label']); ?></p>
                <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100"><?php echo e($card['value']); ?></p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?php echo e($card['hint']); ?></p>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="nr-card p-5 lg:col-span-2">
            <div class="flex items-center justify-between">
                <p class="nr-section-title !text-base"><?php echo e(__('Downloads · last 14 days')); ?></p>
                <span class="nr-badge-slate"><?php echo e(array_sum(array_column($chart, 'total'))); ?></span>
            </div>
            <div class="mt-6 flex h-40 items-end gap-1.5">
                <?php $__currentLoopData = $chart; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bar): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="group relative flex h-full flex-1 items-end" title="<?php echo e($bar['day']); ?>: <?php echo e($bar['total']); ?>">
                        <div class="w-full rounded-t bg-emerald-500/80 transition group-hover:bg-emerald-500 dark:bg-emerald-400/70" style="height: <?php echo e(max(3, round($bar['total'] / $maxDaily * 100))); ?>%"></div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>

        <div class="nr-card p-5">
            <p class="nr-section-title !text-base"><?php echo e(__('Pack format dataset')); ?></p>
            <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100"><?php echo e(number_format($stats['formats'])); ?></p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?php echo e(__('format records')); ?></p>
            <div class="mt-4 border-t border-slate-100 pt-3 text-xs dark:border-slate-800">
                <p class="text-slate-500 dark:text-slate-400"><?php echo e(__('Last sync')); ?></p>
                <p class="mt-1 font-medium text-slate-700 dark:text-slate-300">
                    <?php echo e($lastSync?->created_at?->format('Y-m-d H:i') ?? __('never')); ?>

                    <?php if($lastSync): ?>
                        <span class="nr-badge <?php echo e($lastSync->status === 'success' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'); ?>"><?php echo e($lastSync->status); ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <a href="<?php echo e(route('admin.pack-formats.index')); ?>" class="nr-btn-secondary mt-4 w-full !py-2 text-xs"><?php echo e(__('Manage pack formats')); ?></a>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <div class="nr-card p-5">
            <div class="flex items-center justify-between">
                <p class="nr-section-title !text-base"><?php echo e(__('Recent resource packs')); ?></p>
                <a href="<?php echo e(route('admin.packs.index')); ?>" class="nr-link text-xs"><?php echo e(__('All packs')); ?> →</a>
            </div>
            <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                <?php $__empty_1 = true; $__currentLoopData = $recentPacks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pack): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200"><?php echo e($pack->name); ?></p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"><?php echo e($pack->user?->displayName()); ?> · <?php echo e(nr_bytes((int) ($pack->currentVersion?->file_size ?? 0))); ?></p>
                        </div>
                        <span class="nr-badge shrink-0 <?php echo e($pack->status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'); ?>"><?php echo e($pack->status); ?></span>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400"><?php echo e(__('No packs yet.')); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="nr-card p-5">
            <div class="flex items-center justify-between">
                <p class="nr-section-title !text-base"><?php echo e(__('Recent activity')); ?></p>
                <a href="<?php echo e(route('admin.logs.index')); ?>" class="nr-link text-xs"><?php echo e(__('All logs')); ?> →</a>
            </div>
            <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                <?php $__empty_1 = true; $__currentLoopData = $recentAudit; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="py-2.5">
                        <p class="text-xs font-medium text-slate-700 dark:text-slate-300"><?php echo e($log->description ?: $log->action); ?></p>
                        <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400"><?php echo e($log->actor?->displayName() ?? __('system')); ?> · <?php echo e($log->created_at?->diffForHumans()); ?></p>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400"><?php echo e(__('No audit entries yet.')); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="nr-card mt-4 p-5">
        <div class="flex items-center justify-between">
            <p class="nr-section-title !text-base"><?php echo e(__('Newest users')); ?></p>
            <a href="<?php echo e(route('admin.users.index')); ?>" class="nr-link text-xs"><?php echo e(__('All users')); ?> →</a>
        </div>
        <div class="mt-3 overflow-x-auto">
            <table class="nr-table">
                <thead>
                    <tr>
                        <th class="nr-th"><?php echo e(__('Username')); ?></th>
                        <th class="nr-th"><?php echo e(__('Email')); ?></th>
                        <th class="nr-th"><?php echo e(__('Role')); ?></th>
                        <th class="nr-th"><?php echo e(__('Joined')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $recentUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td class="nr-td"><a href="<?php echo e(route('admin.users.show', $user)); ?>" class="nr-link"><?php echo e($user->displayName()); ?></a></td>
                            <td class="nr-td font-mono text-xs"><?php echo e($user->email); ?></td>
                            <td class="nr-td"><span class="nr-badge-slate"><?php echo e($user->role->value); ?></span></td>
                            <td class="nr-td text-xs text-slate-500"><?php echo e($user->created_at?->format('Y-m-d')); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/next-respack/resources/views/admin/dashboard.blade.php ENDPATH**/ ?>