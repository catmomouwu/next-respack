<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['current' => 0]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['current' => 0]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $steps = [
        0 => __('Welcome'),
        1 => __('Environment'),
        2 => __('Database'),
        3 => __('Storage'),
        4 => __('Site'),
        5 => __('Administrator'),
        6 => __('Confirm'),
        7 => __('Install'),
    ];
?>
<div class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white/80 px-3 py-3 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
    <?php $__currentLoopData = $steps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if($index > 0): ?>
            <span class="text-slate-300 dark:text-slate-700">/</span>
        <?php endif; ?>
        <span class="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-medium
            <?php echo e($index === $current ? 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-slate-950' : ($index < $current ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500')); ?>">
            <span class="flex h-5 w-5 items-center justify-center rounded-full border text-[10px] font-bold
                <?php echo e($index === $current ? 'border-white/40' : ($index < $current ? 'border-emerald-300 dark:border-emerald-700' : 'border-slate-200 dark:border-slate-700')); ?>">
                <?php echo e($index); ?>

            </span>
            <?php echo e($label); ?>

        </span>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php /**PATH /workspace/next-respack/resources/views/components/install-steps.blade.php ENDPATH**/ ?>