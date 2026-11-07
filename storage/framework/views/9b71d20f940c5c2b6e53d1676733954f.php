<?php if(session('status')): ?>
    <div class="nr-success mb-5"><?php echo e(session('status')); ?></div>
<?php endif; ?>

<?php if($errors->any()): ?>
    <div class="nr-error mb-5">
        <p class="font-semibold"><?php echo e(__('Please review the following problems:')); ?></p>
        <ul class="mt-2 list-inside list-disc space-y-1">
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li><?php echo e($error); ?></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    </div>
<?php endif; ?>
<?php /**PATH /workspace/next-respack/resources/views/components/flash.blade.php ENDPATH**/ ?>