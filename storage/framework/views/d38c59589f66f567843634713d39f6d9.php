<?php $__env->startSection('title', __('Environment check')); ?>
<?php $__env->startSection('heading', __('Environment check')); ?>
<?php $__env->startSection('subheading', __('Next Respack verified your server configuration. Required checks must pass before continuing.')); ?>
<?php $__env->startSection('steps'); ?>
    <?php echo $__env->make('components.install-steps', ['current' => 1], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="mb-5 grid gap-3 sm:grid-cols-3">
        <?php $__currentLoopData = [
            ['label' => __('Passed'), 'value' => $summary['pass'], 'class' => 'text-emerald-700 dark:text-emerald-400'],
            ['label' => __('Warnings'), 'value' => $summary['warn'], 'class' => 'text-amber-700 dark:text-amber-400'],
            ['label' => __('Failed'), 'value' => $summary['fail'], 'class' => 'text-red-700 dark:text-red-400'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="nr-card-muted p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?php echo e($card['label']); ?></p>
                <p class="mt-1 text-2xl font-bold <?php echo e($card['class']); ?>"><?php echo e($card['value']); ?></p>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <div class="nr-card overflow-hidden">
        <div class="nr-scrollbar-thin max-h-[28rem] overflow-auto">
            <table class="nr-table">
                <thead class="sticky top-0 bg-white dark:bg-slate-900">
                    <tr>
                        <th class="nr-th"><?php echo e(__('Check')); ?></th>
                        <th class="nr-th"><?php echo e(__('Current value')); ?></th>
                        <th class="nr-th"><?php echo e(__('Status')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td class="nr-td">
                                <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($item['label']); ?></p>
                                <?php if($item['status'] !== 'pass'): ?>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?php echo e($item['fix']); ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="nr-td font-mono text-xs text-slate-600 dark:text-slate-300"><?php echo e($item['value']); ?></td>
                            <td class="nr-td">
                                <?php if($item['status'] === 'pass'): ?>
                                    <span class="nr-badge-green"><?php echo e(__('Passed')); ?></span>
                                <?php elseif($item['status'] === 'warn'): ?>
                                    <span class="nr-badge-amber"><?php echo e(__('Warning')); ?></span>
                                <?php else: ?>
                                    <span class="nr-badge-red"><?php echo e($item['required'] ? __('Failed') : __('Warning')); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <a href="<?php echo e(route('install.welcome')); ?>" class="nr-btn-ghost"><?php echo e(__('Back')); ?></a>
        <div class="flex items-center gap-3">
            <a href="<?php echo e(route('install.requirements')); ?>" class="nr-btn-secondary"><?php echo e(__('Re-check')); ?></a>
            <form method="POST" action="<?php echo e(route('install.requirements.save')); ?>">
                <?php echo csrf_field(); ?>
                <button type="submit" class="nr-btn-primary" <?php if(! $passed): echo 'disabled'; endif; ?>>
                    <?php echo e(__('Continue')); ?>

                </button>
            </form>
        </div>
    </div>
    <?php if(! $passed): ?>
        <p class="mt-4 text-sm text-red-600 dark:text-red-400"><?php echo e(__('Fix all required failures, then re-check the environment.')); ?></p>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.install', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/next-respack/resources/views/install/requirements.blade.php ENDPATH**/ ?>