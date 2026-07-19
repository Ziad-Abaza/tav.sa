<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', '') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js" defer></script>
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md" x-data="publicTestForm()">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <form @submit.prevent="submitForm">
                <div class="mb-4">
                    <input type="text" id="content" x-model="content"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="Enter your message..." required>
                    <p x-show="error" x-text="error" class="mt-1 text-sm text-red-600"></p>
                </div>

                <button type="submit" :disabled="loading"
                    class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 font-medium">
                    <span x-show="!loading">Submit</span>
                    <span x-show="loading">Submitting...</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        function publicTestForm() {
            return {
                content: '',
                loading: false,
                error: '',
                async submitForm() {
                    this.loading = true;
                    this.error = '';

                    try {
                        const response = await fetch('{{ route("public.test.submit") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ content: this.content }),
                        });

                        const data = await response.json();

                        if (data.success) {
                            alert(data.message);
                            this.content = '';
                        } else {
                            this.error = data.message || 'Something went wrong.';
                        }
                    } catch (e) {
                        this.error = 'Request failed. Please try again.';
                    } finally {
                        this.loading = false;
                    }
                }
            };
        }
    </script>
</body>

</html>
