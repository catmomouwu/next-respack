<?php if($paginator->hasPages()): ?>
    <nav class="mt-6 flex flex-wrap items-center justify-between gap-3" aria-label="<?php echo e(__('Pagination')); ?>">
        <p class="text-sm text-slate-500 dark:text-slate-400">
            <?php echo e(__('Showing :from–:to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()])); ?>

        </p>
        <div class="flex flex-wrap items-center gap-1">
            <?php if($paginator->onFirstPage()): ?>
                <span class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-400 dark:border-slate-800 dark:text-slate-600">&laquo;</span>
            <?php else: ?>
                <a href="<?php echo e($paginator->previousPageUrl()); ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">&laquo;</a>
            <?php endif; ?>

            <?php $__currentLoopData = $elements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $element): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(is_string($element)): ?>
                    <span class="px-2 text-sm text-slate-400"><?php echo e($element); ?></span>
                <?php endif; ?>
                <?php if(is_array($element)): ?>
                    <?php $__currentLoopData = $element; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page => $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if($page == $paginator->currentPage()): ?>
                            <span class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white dark:bg-emerald-500 dark:text-slate-950"><?php echo e($page); ?></span>
                        <?php else: ?>
                            <a href="<?php echo e($url); ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?php echo e($page); ?></a>
                        <?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <?php if($paginator->hasMorePages()): ?>
                <a href="<?php echo e($paginator->nextPageUrl()); ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">&raquo;</a>
            <?php else: ?>
                <span class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-400 dark:border-slate-800 dark:text-slate-600">&raquo;</span>
            <?php endif; ?>
        </div>
    </nav>
<?php endif; ?>
<?php /**PATH /workspace/next-respack/resources/views/vendor/pagination/tailwind.blade.php ENDPATH**/ ?>