<?php $__env->startSection('title', __('Users')); ?>
<?php $__env->startSection('heading', __('Users')); ?>
<?php $__env->startSection('content'); ?>
    <form method="GET" action="<?php echo e(route('admin.users.index')); ?>" class="nr-card p-4">
        <div class="grid gap-3 sm:grid-cols-[1.5fr_1fr_1fr_auto]">
            <input class="nr-input" type="search" name="q" value="<?php echo e(request('q')); ?>" placeholder="<?php echo e(__('Username or email…')); ?>">
            <select class="nr-input" name="role">
                <option value=""><?php echo e(__('All roles')); ?></option>
                <?php $__currentLoopData = ['user', 'admin', 'super_admin']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($role); ?>" <?php if(request('role') === $role): echo 'selected'; endif; ?>><?php echo e($role); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
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
                        <th class="nr-th"><?php echo e(__('User')); ?></th>
                        <th class="nr-th"><?php echo e(__('Role')); ?></th>
                        <th class="nr-th"><?php echo e(__('Status')); ?></th>
                        <th class="nr-th"><?php echo e(__('Storage')); ?></th>
                        <th class="nr-th"><?php echo e(__('Joined')); ?></th>
                        <th class="nr-th"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="nr-td">
                                <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($user->displayName()); ?></p>
                                <p class="font-mono text-xs text-slate-500"><?php echo e($user->email); ?></p>
                            </td>
                            <td class="nr-td"><span class="nr-badge-slate"><?php echo e($user->role->value); ?></span></td>
                            <td class="nr-td">
                                <?php if($user->is_active): ?>
                                    <span class="nr-badge-green"><?php echo e(__('Active')); ?></span>
                                <?php else: ?>
                                    <span class="nr-badge-red"><?php echo e(__('Disabled')); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="nr-td text-xs"><?php echo e(nr_bytes((int) ($user->quota?->used_bytes ?? 0))); ?> / <?php echo e(nr_bytes((int) $user->storage_quota)); ?></td>
                            <td class="nr-td text-xs text-slate-500"><?php echo e($user->created_at?->format('Y-m-d')); ?></td>
                            <td class="nr-td text-right"><a href="<?php echo e(route('admin.users.show', $user)); ?>" class="nr-link text-xs"><?php echo e(__('Manage')); ?></a></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="6" class="nr-td py-8 text-center text-sm text-slate-500"><?php echo e(__('No users found.')); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="px-4 pb-4"><?php echo e($users->links()); ?></div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/next-respack/resources/views/admin/users/index.blade.php ENDPATH**/ ?>