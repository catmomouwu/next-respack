<?php $__env->startSection('title', __('Settings')); ?>
<?php $__env->startSection('heading', __('Settings')); ?>
<?php $__env->startSection('content'); ?>
    <form method="POST" action="<?php echo e(route('admin.settings.update')); ?>">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PATCH'); ?>

        <div class="nr-card p-5">
            <p class="nr-section-title"><?php echo e(__('General')); ?></p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="nr-label" for="site_name"><?php echo e(__('Site name')); ?></label>
                    <input class="nr-input" id="site_name" name="site_name" value="<?php echo e(old('site_name', $settings['site_name'])); ?>" required maxlength="120">
                </div>
                <div>
                    <label class="nr-label" for="site_url"><?php echo e(__('Public site URL')); ?></label>
                    <input class="nr-input" id="site_url" name="site_url" type="url" value="<?php echo e(old('site_url', $settings['site_url'])); ?>" required>
                    <p class="nr-help"><?php echo e(__('Must use HTTPS outside localhost. This is also the base for download links.')); ?></p>
                </div>
                <div class="sm:col-span-2">
                    <label class="nr-label" for="site_description"><?php echo e(__('Site description')); ?></label>
                    <textarea class="nr-input" id="site_description" name="site_description" rows="2" maxlength="500"><?php echo e(old('site_description', $settings['site_description'])); ?></textarea>
                </div>
                <div>
                    <label class="nr-label" for="default_locale"><?php echo e(__('Default language')); ?></label>
                    <select class="nr-input" id="default_locale" name="default_locale">
                        <option value="zh_CN" <?php if(old('default_locale', $settings['default_locale']) === 'zh_CN'): echo 'selected'; endif; ?>>简体中文</option>
                        <option value="en" <?php if(old('default_locale', $settings['default_locale']) === 'en'): echo 'selected'; endif; ?>>English</option>
                    </select>
                </div>
                <div>
                    <label class="nr-label" for="timezone"><?php echo e(__('Timezone')); ?></label>
                    <input class="nr-input" id="timezone" name="timezone" value="<?php echo e(old('timezone', $settings['timezone'])); ?>" required>
                </div>
            </div>
        </div>

        <div class="nr-card mt-4 p-5">
            <p class="nr-section-title"><?php echo e(__('Registration & uploads')); ?></p>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="nr-label" for="max_upload_mb"><?php echo e(__('Max upload (MB)')); ?></label>
                    <input class="nr-input" id="max_upload_mb" name="max_upload_mb" type="number" min="1" max="1024" value="<?php echo e(old('max_upload_mb', $settings['max_upload_mb'])); ?>" required>
                </div>
                <div>
                    <label class="nr-label" for="default_quota_mb"><?php echo e(__('Default quota (MB)')); ?></label>
                    <input class="nr-input" id="default_quota_mb" name="default_quota_mb" type="number" min="64" value="<?php echo e(old('default_quota_mb', $settings['default_quota_mb'])); ?>" required>
                </div>
                <div>
                    <label class="nr-label" for="registration_quota_mb"><?php echo e(__('New user quota (MB)')); ?></label>
                    <input class="nr-input" id="registration_quota_mb" name="registration_quota_mb" type="number" min="64" value="<?php echo e(old('registration_quota_mb', $settings['registration_quota_mb'])); ?>" required>
                </div>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="allow_registration" value="1" class="mt-0.5 rounded text-emerald-600" <?php if(old('allow_registration', $settings['allow_registration'])): echo 'checked'; endif; ?>>
                    <span><?php echo e(__('Allow public registration')); ?></span>
                </label>
                <label class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="email_verification" value="1" class="mt-0.5 rounded text-emerald-600" <?php if(old('email_verification', $settings['email_verification'])): echo 'checked'; endif; ?>>
                    <span><?php echo e(__('Require email verification')); ?></span>
                </label>
            </div>
        </div>

        <div class="nr-card mt-4 p-5">
            <p class="nr-section-title"><?php echo e(__('Downloads')); ?></p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="nr-label" for="download_mode"><?php echo e(__('Download mode')); ?></label>
                    <select class="nr-input" id="download_mode" name="download_mode">
                        <option value="php" <?php if(old('download_mode', $settings['download_mode']) === 'php'): echo 'selected'; endif; ?>><?php echo e(__('PHP streaming (works everywhere)')); ?></option>
                        <option value="x-accel" <?php if(old('download_mode', $settings['download_mode']) === 'x-accel'): echo 'selected'; endif; ?>><?php echo e(__('Nginx X-Accel-Redirect')); ?></option>
                    </select>
                    <p class="nr-help"><?php echo e(__('X-Accel requires the Nginx internal location documented in the deployment guide.')); ?></p>
                </div>
                <div>
                    <label class="nr-label" for="x_accel_prefix"><?php echo e(__('X-Accel prefix')); ?></label>
                    <input class="nr-input" id="x_accel_prefix" name="x_accel_prefix" value="<?php echo e(old('x_accel_prefix', $settings['x_accel_prefix'])); ?>">
                </div>
            </div>
            <label class="mt-4 flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" name="download_ip_logging" value="1" class="mt-0.5 rounded text-emerald-600" <?php if(old('download_ip_logging', $settings['download_ip_logging'])): echo 'checked'; endif; ?>>
                <span><?php echo e(__('Store a salted hash of the downloader IP for abuse analysis (never the raw IP)')); ?></span>
            </label>
        </div>

        <div class="nr-card mt-4 p-5">
            <p class="nr-section-title"><?php echo e(__('Minecraft data')); ?></p>
            <label class="mt-4 flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" name="pack_format_auto_sync" value="1" class="mt-0.5 rounded text-emerald-600" <?php if(old('pack_format_auto_sync', $settings['pack_format_auto_sync'])): echo 'checked'; endif; ?>>
                <span><?php echo e(__('Allow a throttled background synchronization from the Minecraft Wiki (at most once per day, triggered by admin visits)')); ?></span>
            </label>
            <p class="nr-help"><?php echo e(__('Manual synchronization always remains available. Cron is optional.')); ?></p>
        </div>

        <div class="nr-card mt-4 p-5">
            <p class="nr-section-title"><?php echo e(__('Email (SMTP)')); ?></p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="nr-label" for="mail_host"><?php echo e(__('SMTP host')); ?></label>
                    <input class="nr-input" id="mail_host" name="mail_host" value="<?php echo e(old('mail_host', $settings['mail_host'])); ?>" autocomplete="off">
                </div>
                <div>
                    <label class="nr-label" for="mail_port"><?php echo e(__('SMTP port')); ?></label>
                    <input class="nr-input" id="mail_port" name="mail_port" type="number" value="<?php echo e(old('mail_port', $settings['mail_port'])); ?>">
                </div>
                <div>
                    <label class="nr-label" for="mail_username"><?php echo e(__('SMTP username')); ?></label>
                    <input class="nr-input" id="mail_username" name="mail_username" value="<?php echo e(old('mail_username', $settings['mail_username'])); ?>" autocomplete="off">
                </div>
                <div>
                    <label class="nr-label" for="mail_password"><?php echo e(__('SMTP password')); ?></label>
                    <input class="nr-input" id="mail_password" name="mail_password" type="password" value="" placeholder="<?php echo e($settings['mail_password_set'] ? __('Leave blank to keep the saved password') : __('Not configured')); ?>" autocomplete="new-password">
                </div>
                <div>
                    <label class="nr-label" for="mail_encryption"><?php echo e(__('Encryption')); ?></label>
                    <select class="nr-input" id="mail_encryption" name="mail_encryption">
                        <?php $__currentLoopData = ['tls' => 'TLS', 'ssl' => 'SSL', 'starttls' => 'STARTTLS', 'none' => __('None')]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($value); ?>" <?php if(old('mail_encryption', $settings['mail_encryption'] ?: 'none') === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div>
                    <label class="nr-label" for="mail_from_address"><?php echo e(__('From address')); ?></label>
                    <input class="nr-input" id="mail_from_address" name="mail_from_address" type="email" value="<?php echo e(old('mail_from_address', $settings['mail_from_address'])); ?>">
                </div>
                <div>
                    <label class="nr-label" for="mail_from_name"><?php echo e(__('From name')); ?></label>
                    <input class="nr-input" id="mail_from_name" name="mail_from_name" value="<?php echo e(old('mail_from_name', $settings['mail_from_name'])); ?>">
                </div>
            </div>
        </div>

        <div class="nr-card mt-4 p-5">
            <p class="nr-section-title"><?php echo e(__('Maintenance')); ?></p>
            <div class="mt-4">
                <label class="nr-label" for="maintenance_message"><?php echo e(__('Maintenance message')); ?></label>
                <textarea class="nr-input" id="maintenance_message" name="maintenance_message" rows="2" maxlength="1000"><?php echo e(old('maintenance_message', $settings['maintenance_message'])); ?></textarea>
                <p class="nr-help"><?php echo e(__('Informational only. Put the application in maintenance mode from the deployment environment when needed.')); ?></p>
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button class="nr-btn-primary !px-6" type="submit"><?php echo e(__('Save settings')); ?></button>
        </div>
    </form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/next-respack/resources/views/admin/settings/edit.blade.php ENDPATH**/ ?>