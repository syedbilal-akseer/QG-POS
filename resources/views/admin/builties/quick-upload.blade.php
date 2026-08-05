<x-app-layout>
    <x-toast />
    <div class="py-6" x-data="quickBiltyUpload()">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mb-1">
                {{ __('Upload Bilty') }}
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                Pick the bilty photo(s)/PDF(s) and send to accounts. No need to know the order or
                customer here — accounts will match it up when they review it.
            </p>

            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Choose files</label>
                <input type="file" multiple accept=".pdf,.png,.jpg,.jpeg"
                       @change="onFilesPicked($event)"
                       class="block w-full text-sm text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md p-1 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary-600 file:text-white hover:file:bg-primary-700">

                <ul x-show="files.length > 0" x-cloak class="mt-4 space-y-1">
                    <template x-for="(f, idx) in files" :key="idx">
                        <li class="flex items-center justify-between text-sm bg-gray-50 dark:bg-gray-700 rounded px-3 py-2">
                            <span class="truncate" x-text="f.name"></span>
                            <button type="button" @click="files.splice(idx, 1)" class="text-red-500 hover:text-red-700 ml-2">
                                <i class="fas fa-times"></i>
                            </button>
                        </li>
                    </template>
                </ul>

                <div x-show="submitErrors.length > 0" x-cloak class="mt-4 rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-3 text-xs text-red-800 dark:text-red-200">
                    <div class="font-semibold mb-1">Some files failed:</div>
                    <ul class="list-disc list-inside">
                        <template x-for="(err, idx) in submitErrors" :key="idx">
                            <li x-text="err"></li>
                        </template>
                    </ul>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="button" @click="submit()" :disabled="files.length === 0 || submitting"
                            class="inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-sm font-medium text-white hover:bg-primary-700 disabled:bg-gray-300 dark:disabled:bg-gray-600 disabled:cursor-not-allowed">
                        <span x-show="!submitting"><i class="fas fa-paper-plane mr-1"></i>Send to Accounts</span>
                        <span x-show="submitting"><i class="fas fa-spinner fa-spin mr-1"></i>Uploading…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function quickBiltyUpload() {
            return {
                files: [],
                submitting: false,
                submitErrors: [],

                onFilesPicked(e) {
                    const picked = Array.from(e.target.files || []);
                    this.files.push(...picked.slice(0, 50 - this.files.length));
                    e.target.value = '';
                },

                async submit() {
                    if (this.files.length === 0 || this.submitting) return;
                    this.submitting = true;
                    this.submitErrors = [];

                    const fd = new FormData();
                    this.files.forEach(f => fd.append('files[]', f));

                    try {
                        const r = await fetch("{{ route('builties.quickStore') }}", {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                            body: fd,
                        });
                        if (r.redirected) {
                            window.location.href = r.url;
                            return;
                        }
                        if (!r.ok) {
                            this.submitErrors = ['Upload failed (HTTP ' + r.status + ')'];
                        }
                    } catch (e) {
                        this.submitErrors = [e.message];
                    } finally {
                        this.submitting = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>
