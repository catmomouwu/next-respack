<?php $__env->startSection('title', __('Page not found')); ?>
<?php $__env->startSection('content'); ?>
    <div class="mx-auto max-w-md py-20 text-center">
        <p class="text-6xl font-extrabold text-slate-200 dark:text-slate-800">404</p>
        <h1 class="mt-3 text-2xl font-bold text-slate-900 dark:text-slate-100"><?php echo e(__('Page not found')); ?></h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400"><?php echo e(__('The page or download link you requested does not exist or has been removed.')); ?></p>
        <a href="<?php echo e(route('home')); ?>" class="nr-btn-primary mt-5"><?php echo e(__('Back to home')); ?></a>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/next-respack/resources/views/errors/404.blade.php ENDPATH**/ ?>