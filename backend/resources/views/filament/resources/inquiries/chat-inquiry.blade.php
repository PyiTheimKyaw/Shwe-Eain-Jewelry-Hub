<x-filament-panels::page>
    @if (! $showChat)
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Chat is unavailable. Ensure <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-800">FIREBASE_CREDENTIALS</code>,
                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-800">FIREBASE_PROJECT_ID</code>, and
                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-800">FIREBASE_WEB_API_KEY</code> are set, Firestore rules are deployed, and this inquiry has a thread id.
            </p>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                Thread: <code class="text-xs">{{ $threadId }}</code>
            </div>
            <div
                id="jh-messages"
                class="mb-4 max-h-[480px] min-h-[280px] overflow-y-auto rounded-lg border border-gray-200 bg-gray-950/40 p-3 dark:border-gray-700"
                wire:ignore
            ></div>
            <form id="jh-form" class="flex flex-wrap gap-2" wire:ignore>
                <input
                    id="jh-input"
                    type="text"
                    maxlength="4000"
                    class="min-w-[200px] flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    placeholder="{{ __('Type a message to the customer') }}"
                />
                <button
                    type="submit"
                    class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-amber-400"
                >
                    {{ __('Send') }}
                </button>
            </form>
        </x-filament::section>

        @php
            // Read from Laravel config here — not from Livewire public array props (arrays can dehydrate
            // to [] in Filament/Livewire, which causes auth/configuration-not-found in the browser).
            $__firebaseWebConfig = [
                'apiKey' => trim((string) config('services.firebase.web_api_key')),
                'authDomain' => trim((string) config('services.firebase.project_id')).'.firebaseapp.com',
                'projectId' => trim((string) config('services.firebase.project_id')),
                'storageBucket' => (string) config('services.firebase.storage_bucket'),
                'messagingSenderId' => (string) config('services.firebase.messaging_sender_id'),
                'appId' => (string) config('services.firebase.web_app_id'),
            ];
        @endphp
        <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js"></script>
        <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-auth-compat.js"></script>
        <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore-compat.js"></script>
        <script>
            (function () {
                const firebaseConfig = @json($__firebaseWebConfig);
                if (!firebaseConfig.apiKey || !firebaseConfig.projectId) {
                    document.getElementById('jh-messages').textContent =
                        'Firebase web config is incomplete. Set FIREBASE_WEB_API_KEY and FIREBASE_PROJECT_ID in backend .env (same values as the Firebase Web app), then run: php artisan config:clear';
                    return;
                }
                if (!firebase.apps.length) {
                    firebase.initializeApp(firebaseConfig);
                }
                const token = @json($firebaseToken);
                const threadId = @json($threadId);
                const box = document.getElementById('jh-messages');
                const form = document.getElementById('jh-form');
                const input = document.getElementById('jh-input');

                function row(msg) {
                    const role = msg.sender_role || '';
                    const text = msg.text || '';
                    const wrap = document.createElement('div');
                    wrap.className = 'mb-2 text-sm ' + (role === 'admin' ? 'text-right' : 'text-left');
                    wrap.innerHTML =
                        '<span class="inline-block max-w-[85%] rounded-lg border border-amber-500/30 bg-gray-900/80 px-3 py-2 text-gray-100">' +
                        (role === 'admin' ? '<span class="text-xs text-amber-400">Shop</span><br/>' : '<span class="text-xs text-gray-400">Customer</span><br/>') +
                        text.replace(/</g, '&lt;') +
                        '</span>';
                    return wrap;
                }

                firebase
                    .auth()
                    .signInWithCustomToken(token)
                    .then(function () {
                        const ref = firebase
                            .firestore()
                            .collection('inquiry_threads')
                            .doc(threadId)
                            .collection('messages')
                            .orderBy('created_at', 'asc');
                        ref.onSnapshot(function (snap) {
                            box.innerHTML = '';
                            snap.forEach(function (d) {
                                box.appendChild(row(d.data()));
                            });
                            box.scrollTop = box.scrollHeight;
                        });
                    })
                    .catch(function (e) {
                        box.textContent = 'Firebase error: ' + e;
                    });

                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const text = (input.value || '').trim();
                    if (!text) return;
                    firebase
                        .firestore()
                        .collection('inquiry_threads')
                        .doc(threadId)
                        .collection('messages')
                        .add({
                            text: text,
                            sender_role: 'admin',
                            created_at: firebase.firestore.FieldValue.serverTimestamp(),
                        })
                        .then(function () {
                            input.value = '';
                        })
                        .catch(function (err) {
                            alert('Send failed: ' + err);
                        });
                });
            })();
        </script>
    @endif
</x-filament-panels::page>
