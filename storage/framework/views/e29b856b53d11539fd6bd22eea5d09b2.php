<?php $__env->startSection('title', __('Home')); ?>
<?php $__env->startSection('content'); ?>
    <section class="grid items-center gap-10 py-6 lg:grid-cols-[1.25fr_1fr] lg:py-12">
        <div>
            <span class="nr-badge-green">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                <?php echo e(__('Self-hosted · Minecraft Java Edition')); ?>

            </span>
            <h1 class="mt-4 text-4xl font-extrabold leading-[1.1] tracking-tight text-slate-900 sm:text-5xl dark:text-slate-100">
                <?php echo e(__('Your resource packs,')); ?><br>
                <span class="text-emerald-600 dark:text-emerald-400"><?php echo e(__('properly hosted and validated.')); ?></span>
            </h1>
            <p class="mt-5 max-w-2xl text-base leading-relaxed text-slate-600 dark:text-slate-300">
                <?php echo e(__('Upload Minecraft Java Edition resource pack ZIP files, validate their structure, compute SHA-1, detect pack formats and generate anonymous HTTPS download links with server.properties snippets.')); ?>

            </p>

            <div class="mt-7 flex flex-wrap items-center gap-3">
                <?php if(auth()->guard()->check()): ?>
                    <a href="<?php echo e(route('packs.create')); ?>" class="nr-btn-primary !px-6 !py-3"><?php echo e(__('Upload a resource pack')); ?></a>
                    <a href="<?php echo e(route('dashboard')); ?>" class="nr-btn-secondary !px-6 !py-3"><?php echo e(__('Open dashboard')); ?></a>
                <?php else: ?>
                    <?php if(nr_setting_bool('allow_registration', true)): ?>
                        <a href="<?php echo e(route('register')); ?>" class="nr-btn-primary !px-6 !py-3"><?php echo e(__('Get started')); ?></a>
                    <?php endif; ?>
                    <a href="<?php echo e(route('login')); ?>" class="nr-btn-secondary !px-6 !py-3"><?php echo e(__('Sign in')); ?></a>
                <?php endif; ?>
                <a href="<?php echo e(route('pack-formats.index')); ?>" class="nr-btn-ghost"><?php echo e(__('Browse pack formats')); ?> →</a>
            </div>

            <div class="mt-9 grid max-w-xl grid-cols-2 gap-3 sm:grid-cols-4">
                <?php $__currentLoopData = [
                    ['value' => $stats['packs'], 'label' => __('Active packs')],
                    ['value' => $stats['downloads'], 'label' => __('Downloads')],
                    ['value' => $stats['formats'], 'label' => __('Formats')],
                    ['value' => $stats['versions'], 'label' => __('Mapped versions')],
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="nr-card-muted px-4 py-3">
                        <p class="text-xl font-bold text-slate-900 dark:text-slate-100"><?php echo e(number_format($stat['value'])); ?></p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"><?php echo e($stat['label']); ?></p>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>

        <div class="relative">
            <div class="nr-card overflow-hidden">
                <div class="border-b border-slate-200 bg-slate-50 px-5 py-3 dark:border-slate-800 dark:bg-slate-900/60">
                    <div class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                        <span class="ml-2 font-mono text-xs text-slate-500 dark:text-slate-400">server.properties</span>
                    </div>
                </div>
                <pre class="overflow-x-auto p-5 font-mono text-xs leading-6 text-slate-600 dark:text-slate-300"><code>resource-pack=https://your-domain.example/dl/&lt;token&gt;
resource-pack-sha1=4f2a9c8e6b1d3f5a7c9e0b2d4f6a8c1e3b5d7f9a
require-resource-pack=true</code></pre>
            </div>
            <div class="nr-card mt-4 p-5">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200"><?php echo e(__('Validation pipeline')); ?></p>
                    <span class="nr-badge-green"><?php echo e(__('6 checks')); ?></span>
                </div>
                <ul class="mt-3 space-y-2 text-xs text-slate-600 dark:text-slate-300">
                    <?php $__currentLoopData = [
                        __('ZIP signature, CRC and integrity'),
                        __('Zip Slip, symlink and ZIP bomb protection'),
                        __('Root pack.mcmeta and JSON schema'),
                        __('Pack format detection against the wiki dataset'),
                        __('SHA-1 of the final stored file'),
                        __('Anonymous, revocable download token'),
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li class="flex items-center gap-2">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            <?php echo e($item); ?>

                        </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        </div>
    </section>

    <section class="mt-12">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="nr-section-title"><?php echo e(__('Latest pack format mappings')); ?></h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?php echo e(__('Sourced from the Minecraft Wiki pack format history.')); ?></p>
            </div>
            <a href="<?php echo e(route('pack-formats.index')); ?>" class="nr-link text-sm"><?php echo e(__('View full database')); ?> →</a>
        </div>

        <?php if($latestFormats->isEmpty()): ?>
            <div class="nr-card mt-4 p-8 text-center text-sm text-slate-500 dark:text-slate-400">
                <?php echo e(__('Pack format data has not been imported yet. An administrator can synchronize it from the admin panel.')); ?>

            </div>
        <?php else: ?>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <?php $__currentLoopData = $latestFormats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $format): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e(route('pack-formats.index', ['format' => $format->format_value])); ?>" class="nr-card group p-4 transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md dark:hover:border-emerald-700">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?php echo e(__('Format')); ?></p>
                        <p class="mt-1 font-mono text-2xl font-bold text-slate-900 group-hover:text-emerald-600 dark:text-slate-100 dark:group-hover:text-emerald-400"><?php echo e($format->format_value); ?></p>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            <?php echo e($format->versions->take(3)->pluck('version_name')->implode(', ')); ?>

                            <?php if($format->versions->count() > 3): ?>
                                +<?php echo e($format->versions->count() - 3); ?>

                            <?php endif; ?>
                        </p>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="mt-12">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="nr-section-title"><?php echo e(__('Recently published resource packs')); ?></h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?php echo e(__('Pack names are listed for discovery. Downloads are only available through the anonymous link shared by the owner.')); ?></p>
            </div>
        </div>

        <?php if($latestPacks->isEmpty()): ?>
            <div class="nr-card mt-4 p-8 text-center text-sm text-slate-500 dark:text-slate-400">
                <?php echo e(__('No resource packs have been published yet.')); ?>

            </div>
        <?php else: ?>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <?php $__currentLoopData = $latestPacks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pack): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="nr-card p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-900 dark:text-slate-100"><?php echo e($pack->name); ?></p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?php echo e($pack->user?->displayName()); ?> · <?php echo e($pack->created_at?->diffForHumans()); ?></p>
                            </div>
                            <span class="nr-badge-slate shrink-0"><?php echo e(__('Format')); ?> <?php echo e($pack->displayFormat()); ?></span>
                        </div>
                        <?php if($pack->description): ?>
                            <p class="mt-3 line-clamp-2 text-sm text-slate-600 dark:text-slate-300"><?php echo e($pack->description); ?></p>
                        <?php endif; ?>
                        <p class="mt-3 font-mono text-xs text-slate-400"><?php echo e(nr_bytes((int) ($pack->currentVersion?->file_size ?? 0))); ?></p>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/next-respack/resources/views/home.blade.php ENDPATH**/ ?>