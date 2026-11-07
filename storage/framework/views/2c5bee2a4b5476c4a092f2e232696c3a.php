<?php $__env->startSection('title', __('Audit & download logs')); ?>
<?php $__env->startSection('heading', __('Audit & download logs')); ?>
<?php $__env->startSection('content'); ?>
    <form method="GET" action="<?php echo e(route('admin.logs.index')); ?>" class="nr-card p-4">
        <div class="grid gap-3 sm:grid-cols-[1.6fr_1fr_auto]">
            <input class="nr-input" type="search" name="q" value="<?php echo e(request('q')); ?>" placeholder="<?php echo e(__('Action, description or username…')); ?>">
            <select class="nr-input" name="action">
                <option value=""><?php echo e(__('All actions')); ?></option>
                <?php $__currentLoopData = $actions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $action): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($action); ?>" <?php if(request('action') === $action): echo 'selected'; endif; ?>><?php echo e($action); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <button class="nr-btn-primary" type="submit"><?php echo e(__('Filter')); ?></button>
        </div>
    </form>

    <div class="nr-card mt-4 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <p class="nr-section-title !text-base"><?php echo e(__('Audit trail')); ?></p>
        </div>
        <div class="overflow-x-auto">
            <table class="nr-table">
                <thead>
                    <tr>
                        <th class="nr-th"><?php echo e(__('Action')); ?></th>
                        <th class="nr-th"><?php echo e(__('Description')); ?></th>
                        <th class="nr-th"><?php echo e(__('Actor')); ?></th>
                        <th class="nr-th"><?php echo e(__('IP hash')); ?></th>
                        <th class="nr-th"><?php echo e(__('Time')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="nr-td font-mono text-xs"><?php echo e($log->action); ?></td>
                            <td class="nr-td">
                                <p class="text-sm"><?php echo e($log->description); ?></p>
                                <?php if($log->metadata): ?>
                                    <p class="mt-1 break-all text-[11px] text-slate-400"><?php echo e(\Illuminate\Support\Str::limit(json_encode($log->metadata, JSON_UNESCAPED_UNICODE), 140)); ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="nr-td text-xs"><?php echo e($log->actor?->displayName() ?? __('system')); ?></td>
                            <td class="nr-td font-mono text-[11px] text-slate-400"><?php echo e($log->ip_hash ? substr($log->ip_hash, 0, 12).'…' : '—'); ?></td>
                            <td class="nr-td text-xs text-slate-500"><?php echo e($log->created_at?->format('Y-m-d H:i:s')); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="5" class="nr-td py-8 text-center text-sm text-slate-500"><?php echo e(__('No audit entries.')); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="px-4 pb-4"><?php echo e($logs->links()); ?></div>
    </div>

    <div class="nr-card mt-4 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <p class="nr-section-title !text-base"><?php echo e(__('Download log')); ?></p>
        </div>
        <div class="overflow-x-auto">
            <table class="nr-table">
                <thead>
                    <tr>
                        <th class="nr-th"><?php echo e(__('Pack')); ?></th>
                        <th class="nr-th"><?php echo e(__('Version')); ?></th>
                        <th class="nr-th"><?php echo e(__('Method')); ?></th>
                        <th class="nr-th"><?php echo e(__('Status')); ?></th>
                        <th class="nr-th"><?php echo e(__('Range')); ?></th>
                        <th class="nr-th"><?php echo e(__('Bytes')); ?></th>
                        <th class="nr-th"><?php echo e(__('Time')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $downloads; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $download): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="nr-td text-xs"><?php echo e($download->pack?->name ?? '—'); ?></td>
                            <td class="nr-td text-xs"><?php echo e($download->version?->version_number ?? '—'); ?></td>
                            <td class="nr-td font-mono text-xs"><?php echo e($download->method); ?></td>
                            <td class="nr-td"><span class="nr-badge-slate"><?php echo e($download->status_code); ?></span></td>
                            <td class="nr-td font-mono text-[11px]"><?php echo e($download->range_header ?: '—'); ?></td>
                            <td class="nr-td text-xs"><?php echo e(nr_bytes((int) $download->bytes_sent)); ?></td>
                            <td class="nr-td text-xs text-slate-500"><?php echo e($download->created_at?->format('Y-m-d H:i')); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="7" class="nr-td py-8 text-center text-sm text-slate-500"><?php echo e(__('No downloads recorded.')); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="px-4 pb-4"><?php echo e($downloads->links()); ?></div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/next-respack/resources/views/admin/logs/index.blade.php ENDPATH**/ ?>