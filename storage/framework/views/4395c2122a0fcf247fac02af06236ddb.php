<?php $__env->startSection('title', __('Minecraft pack formats')); ?>
<?php $__env->startSection('content'); ?>
    <div>
        <span class="nr-badge-green"><?php echo e(__('Minecraft Java Edition · Resource Pack Format')); ?></span>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100"><?php echo e(__('Pack format database')); ?></h1>
        <p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            <?php echo e(__('Search resource pack formats and their Minecraft Java Edition versions. Data is collected from the Minecraft Wiki pack format page and includes releases, snapshots, pre-releases and release candidates.')); ?>

        </p>
        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
            <span><?php echo e(__(':count mapped versions', ['count' => number_format($totalVersions)])); ?></span>
            <span>·</span>
            <span><?php echo e(__('Updated')); ?>: <?php echo e($lastSync?->created_at?->format('Y-m-d H:i') ?? __('bundled data')); ?></span>
            <span>·</span>
            <a href="https://minecraft.wiki/w/Pack_format" target="_blank" rel="noopener" class="nr-link"><?php echo e(__('Data source')); ?></a>
            <span>·</span>
            <a href="<?php echo e(route('pack-formats.api')); ?>" class="nr-link">JSON API</a>
        </div>
    </div>

    <form method="GET" action="<?php echo e(route('pack-formats.index')); ?>" class="nr-card mt-6 p-4">
        <div class="grid gap-3 sm:grid-cols-[1.5fr_1fr_auto]">
            <input class="nr-input" type="search" name="q" value="<?php echo e($q); ?>" placeholder="<?php echo e(__('Search version (e.g. 1.21.9) or format (e.g. 69)…')); ?>">
            <select class="nr-input" name="type">
                <option value=""><?php echo e(__('All version types')); ?></option>
                <?php $__currentLoopData = $types; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($value); ?>" <?php if($type === $value): echo 'selected'; endif; ?>>
                        <?php echo e(['release' => __('Release'), 'snapshot' => __('Snapshot'), 'prerelease' => __('Pre-release'), 'release_candidate' => __('Release candidate'), 'experimental' => __('Experimental')][$value] ?? $value); ?>

                    </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <div class="flex gap-2">
                <button class="nr-btn-primary" type="submit"><?php echo e(__('Search')); ?></button>
                <?php if($q !== '' || $type !== '' || $format !== ''): ?>
                    <a href="<?php echo e(route('pack-formats.index')); ?>" class="nr-btn-ghost"><?php echo e(__('Clear')); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php if($format !== ''): ?>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400"><?php echo e(__('Filtering by format :format', ['format' => $format])); ?></p>
        <?php endif; ?>
    </form>

    <?php if($formats->isEmpty()): ?>
        <div class="nr-card mt-4 p-10 text-center text-sm text-slate-500 dark:text-slate-400">
            <?php echo e(__('No matching format records. An administrator can synchronize the dataset from the admin panel.')); ?>

        </div>
    <?php else: ?>
        <div class="nr-card mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="nr-table">
                    <thead>
                        <tr>
                            <th class="nr-th"><?php echo e(__('Resource pack format')); ?></th>
                            <th class="nr-th"><?php echo e(__('Minecraft Java Edition versions')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $formats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $formatRow): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td class="nr-td align-top">
                                    <p class="font-mono text-lg font-bold text-slate-900 dark:text-slate-100"><?php echo e($formatRow->format_value); ?></p>
                                    <p class="mt-1 text-[11px] text-slate-400"><?php echo e(__('Major :major · Minor :minor', ['major' => (string) $formatRow->format_major, 'minor' => $formatRow->format_minor === null ? '—' : (string) $formatRow->format_minor])); ?></p>
                                </td>
                                <td class="nr-td">
                                    <div class="flex flex-wrap gap-1.5">
                                        <?php $__empty_1 = true; $__currentLoopData = $formatRow->versions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $versionRow): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                            <span class="nr-badge <?php echo e(match ($versionRow->version_type) {
                                                'release' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                                                'snapshot' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
                                                'prerelease' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                                                'release_candidate' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
                                                default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                                            }); ?>">
                                                <?php echo e($versionRow->version_name); ?>

                                            </span>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                            <span class="text-xs text-slate-400">—</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
            <div class="px-4 pb-4"><?php echo e($formats->links()); ?></div>
        </div>
    <?php endif; ?>

    <div class="nr-warning mt-6">
        <?php echo e(__('Format compatibility is necessary but not sufficient. Textures, models, sounds and other resources in a pack may still be incompatible with a given Minecraft version.')); ?>

    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/next-respack/resources/views/pack-formats/index.blade.php ENDPATH**/ ?>