<?php $__env->startSection('title', __('Resource packs')); ?>
<?php $__env->startSection('heading', __('Resource packs')); ?>
<?php $__env->startSection('content'); ?>
    <form method="GET" action="<?php echo e(route('admin.packs.index')); ?>" class="nr-card p-4">
        <div class="grid gap-3 sm:grid-cols-[1.6fr_1fr_auto]">
            <input class="nr-input" type="search" name="q" value="<?php echo e(request('q')); ?>" placeholder="<?php echo e(__('Pack name, owner username or email…')); ?>">
            <select class="nr-input" name="status">
                <option value=""><?php echo e(__('All statuses')); ?></option>
                <option value="active" <?php if(request('status') === 'active'): echo 'selected'; endif; ?>><?php echo e(__('Active')); ?></option>
                <option value="disabled" <?php if(request('status') === 'disabled'): echo 'selected'; endif; ?>><?php echo e(__('Disabled')); ?></option>
            </select>
            <button class="nr-btn-primary" type="submit"><?php echo e(__('Filter')); ?></button>
        </div>
    </form>

    <div class="nr-card mt-4 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="nr-table">
                <thead>
                    <tr>
                        <th class="nr-th"><?php echo e(__('Pack')); ?></th>
                        <th class="nr-th"><?php echo e(__('Owner')); ?></th>
                        <th class="nr-th"><?php echo e(__('Format')); ?></th>
                        <th class="nr-th"><?php echo e(__('Versions')); ?></th>
                        <th class="nr-th"><?php echo e(__('Size')); ?></th>
                        <th class="nr-th"><?php echo e(__('Status')); ?></th>
                        <th class="nr-th"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $packs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pack): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="nr-td">
                                <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($pack->name); ?></p>
                                <p class="text-[11px] text-slate-500"><?php echo e($pack->created_at?->format('Y-m-d H:i')); ?></p>
                            </td>
                            <td class="nr-td text-xs">
                                <?php if($pack->user): ?>
                                    <a href="<?php echo e(route('admin.users.show', $pack->user)); ?>" class="nr-link"><?php echo e($pack->user->displayName()); ?></a>
                                <?php else: ?>
                                    <span class="text-slate-400"><?php echo e(__('deleted')); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="nr-td font-mono text-xs"><?php echo e($pack->displayFormat()); ?></td>
                            <td class="nr-td text-xs"><?php echo e($pack->versions_count); ?></td>
                            <td class="nr-td text-xs"><?php echo e(nr_bytes((int) $pack->totalSize())); ?></td>
                            <td class="nr-td">
                                <span class="nr-badge <?php echo e($pack->status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'); ?>"><?php echo e($pack->status); ?></span>
                            </td>
                            <td class="nr-td">
                                <div class="flex justify-end gap-1.5">
                                    <form method="POST" action="<?php echo e(route('admin.packs.toggle', $pack)); ?>">
                                        <?php echo csrf_field(); ?>
                                        <button class="nr-btn-secondary !px-2 !py-1 text-[11px]" type="submit"><?php echo e($pack->status === 'active' ? __('Disable') : __('Enable')); ?></button>
                                    </form>
                                    <form method="POST" action="<?php echo e(route('admin.packs.destroy', $pack)); ?>" onsubmit="return confirm('<?php echo e(__('Delete this pack and its files?')); ?>')">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('DELETE'); ?>
                                        <button class="nr-btn-danger !px-2 !py-1 text-[11px]" type="submit"><?php echo e(__('Delete')); ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="7" class="nr-td py-8 text-center text-sm text-slate-500"><?php echo e(__('No resource packs found.')); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="px-4 pb-4"><?php echo e($packs->links()); ?></div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/next-respack/resources/views/admin/packs/index.blade.php ENDPATH**/ ?>