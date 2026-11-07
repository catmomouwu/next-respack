<?php $__env->startSection('title', __('Verify your email')); ?>
<?php $__env->startSection('content'); ?>
    <div class="mx-auto max-w-lg py-10">
        <div class="nr-card p-7 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6.5 12 13l9-6.5M4 19h16a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z"/></svg>
            </span>
            <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"><?php echo e(__('Verify your email address')); ?></h1>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                <?php echo e(__('We sent a verification link to :email. Open it to activate upload and download features.', ['email' => auth()->user()->email])); ?>

            </p>

            <form method="POST" action="<?php echo e(route('verification.send')); ?>" class="mt-6">
                <?php echo csrf_field(); ?>
                <button type="submit" class="nr-btn-primary w-full"><?php echo e(__('Resend verification email')); ?></button>
            </form>

            <div class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-800">
                <form method="POST" action="<?php echo e(route('logout')); ?>">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="text-sm text-slate-500 hover:text-red-600 dark:text-slate-400"><?php echo e(__('Sign out')); ?></button>
                </form>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/next-respack/resources/views/auth/verify-email.blade.php ENDPATH**/ ?>