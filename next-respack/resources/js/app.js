import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('themeToggle', () => ({
        dark: document.documentElement.classList.contains('dark'),
        toggle() {
            this.dark = !this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            localStorage.setItem('nr-theme', this.dark ? 'dark' : 'light');
        },
    }));

    Alpine.data('copyText', () => ({
        copied: false,
        async copy(text) {
            try {
                await navigator.clipboard.writeText(text);
            } catch (error) {
                const area = document.createElement('textarea');
                area.value = text;
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                document.body.removeChild(area);
            }
            this.copied = true;
            setTimeout(() => {
                this.copied = false;
            }, 1800);
        },
    }));

    Alpine.data('uploadForm', () => ({
        uploading: false,
        progress: 0,
        filename: '',
        submit(event) {
            const input = event.target.querySelector('input[type="file"]');
            if (!input || !input.files || input.files.length === 0) {
                return;
            }

            this.filename = input.files[0].name;
            this.uploading = true;
            this.progress = 0;

            const form = event.target;
            const data = new FormData(form);
            const xhr = new XMLHttpRequest();
            xhr.open(form.method || 'POST', form.action, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            const token = form.querySelector('input[name="_token"]');
            if (token) {
                xhr.setRequestHeader('X-CSRF-TOKEN', token.value);
            }
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    this.progress = Math.round((e.loaded / e.total) * 100);
                }
            });
            xhr.addEventListener('load', () => {
                if (xhr.status >= 200 && xhr.status < 400 && xhr.responseURL) {
                    window.location.href = xhr.responseURL;
                } else {
                    this.uploading = false;
                    window.location.reload();
                }
            });
            xhr.addEventListener('error', () => {
                this.uploading = false;
                form.submit();
            });
            xhr.send(data);
        },
    }));

    Alpine.data('installerTest', (endpoint, payload = {}) => ({
        testing: false,
        ok: null,
        errors: [],
        warnings: [],
        async run(form) {
            this.testing = true;
            this.errors = [];
            this.warnings = [];
            this.ok = null;

            const body = new FormData(form);
            Object.entries(payload).forEach(([key, value]) => body.set(key, value));

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        Accept: 'application/json',
                    },
                    body,
                });
                const data = await response.json();
                this.ok = Boolean(data.ok);
                this.errors = data.errors || [];
                this.warnings = data.warnings || [];
                window.dispatchEvent(new CustomEvent('installer-test-result', { detail: data }));
            } catch (error) {
                this.ok = false;
                this.errors = [error.message];
            } finally {
                this.testing = false;
            }
        },
    }));
});

Alpine.start();
