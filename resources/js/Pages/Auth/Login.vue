<script setup>
import { useForm, Head } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Login — ClubSync" />
    <div class="min-h-screen flex items-center justify-center bg-white px-6">
        <div class="w-full max-w-sm">
            <h1 class="text-2xl font-bold text-green-900 mb-6">Sign in to ClubSync</h1>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input v-model="form.email" type="email" required
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-green-600" />
                    <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input v-model="form.password" type="password" required
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-green-600" />
                    <p v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</p>
                </div>

                <button type="submit" :disabled="form.processing"
                    class="w-full bg-green-800 text-white font-semibold py-3 rounded-full hover:bg-green-900 transition-colors disabled:opacity-60">
                    {{ form.processing ? 'Signing in…' : 'Sign In' }}
                </button>
            </form>
        </div>
    </div>
</template>
