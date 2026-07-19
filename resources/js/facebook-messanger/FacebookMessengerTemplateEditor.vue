<template>
    <div class="min-h-screen pb-20">
        <div class="mx-auto">
            <!-- Page title -->
            <div class="mb-6">
                <h1
                    class="text-2xl font-semibold text-secondary-700 dark:text-secondary-300"
                >
                    {{
                        isEditMode
                            ? "Update Messenger Template"
                            : "Create Messenger Template"
                    }}
                </h1>
            </div>

            <!-- Facebook Messenger info banner -->
            <div class="bg-blue-50 border-l-4 border-blue-500 dark:bg-blue-900/20 dark:border-blue-400 rounded-r-md p-4 shadow-sm mb-4">
                <div class="flex items-start gap-3">
                    <!-- Messenger gradient icon -->
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.477 2 2 6.145 2 11.243c0 2.906 1.404 5.502 3.6 7.21V22l3.28-1.803A10.52 10.52 0 0012 20.485c5.523 0 10-4.145 10-9.242C22 6.145 17.523 2 12 2z" fill="url(#msngr-grad)"/>
                        <path d="M13.197 14.3l-2.545-2.72-4.966 2.72 5.463-5.8 2.608 2.72 4.903-2.72-5.463 5.8z" fill="white"/>
                        <defs><linearGradient id="msngr-grad" x1="2" y1="12" x2="22" y2="12" gradientUnits="userSpaceOnUse"><stop stop-color="#0099FF"/><stop offset="1" stop-color="#A033FF"/></linearGradient></defs>
                    </svg>
                    <div>
                        <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-300">
                            Facebook Messenger Template
                        </h3>
                        <p class="text-xs text-blue-700 dark:text-blue-400 mt-0.5">
                            Create reusable message templates for Messenger broadcasts and automated replies. Use <strong>variables</strong> like <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{1}}</code> to personalise messages with contact data.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Two-column layout -->
            <div class="grid grid-cols-1 xl:grid-cols-6 gap-6 mb-6">
                <!-- ── Form card ───────────────────────────────────────── -->
                <div
                    class="bg-white border border-slate-300 rounded-lg xl:col-span-4 dark:bg-transparent dark:ring-slate-600 dark:border-slate-600"
                >
                    <!-- 1. Template Info -->
                    <div
                        class="border-b border-slate-300 px-6 py-5 dark:border-slate-600"
                    >
                        <!-- Section header -->
                        <div class="border border-slate-300 dark:border-slate-600 px-3 py-3 sm:px-4 rounded-lg mb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-200">Template Information</h2>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Give your template a unique name and choose a content type</p>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <!-- Name -->
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                >
                                    Template Name
                                    <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    maxlength="255"
                                    placeholder="e.g. welcome_message"
                                    class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    :class="{
                                        'border-red-400 focus:border-red-500 focus:ring-red-500':
                                            errors.name,
                                    }"
                                />
                                <p
                                    v-if="errors.name"
                                    class="mt-1 text-xs text-red-500"
                                >
                                    {{ errors.name }}
                                </p>
                            </div>

                            <!-- Content Type -->
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                >
                                    Content Type
                                    <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.content_type"
                                    class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    :class="{
                                        'border-red-400': errors.content_type,
                                    }"
                                    @change="onContentTypeChange"
                                >
                                    <option value="text">Text</option>
                                    <option value="image">Image</option>
                                    <option value="video">Video</option>
                                    <option value="document">Document</option>
                                </select>
                                <p
                                    v-if="errors.content_type"
                                    class="mt-1 text-xs text-red-500"
                                >
                                    {{ errors.content_type }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Media upload (shown when content_type ≠ text) -->
                    <div
                        v-if="form.content_type !== 'text'"
                        class="border-b border-slate-300 px-6 py-5 dark:border-slate-600"
                    >
                        <!-- Section header -->
                        <div class="border border-slate-300 dark:border-slate-600 px-3 py-3 sm:px-4 rounded-lg mb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-200">{{ contentTypeLabel }} File</h2>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ mediaTypeHint }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Uploaded / existing file preview -->
                        <div
                            v-if="form.media_url && !localFile"
                            class="mb-4 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden"
                        >
                            <!-- Image preview -->
                            <img
                                v-if="form.content_type === 'image'"
                                :src="form.media_url"
                                class="w-full max-h-48 object-cover"
                                alt="Media preview"
                                @error="
                                    (e) => (e.target.style.display = 'none')
                                "
                            />
                            <!-- Video preview -->
                            <video
                                v-else-if="form.content_type === 'video'"
                                :src="form.media_url"
                                class="w-full max-h-48"
                                controls
                            ></video>
                            <!-- Document / audio label -->
                            <div v-else class="flex items-center gap-3 p-3">
                                <div
                                    class="w-8 h-8 rounded bg-blue-100 dark:bg-blue-900 flex items-center justify-center flex-shrink-0"
                                >
                                    <component
                                        :is="mediaIcon"
                                        class="w-4 h-4 text-blue-600 dark:text-blue-400"
                                    />
                                </div>
                                <p
                                    class="text-sm text-gray-700 dark:text-gray-300 truncate"
                                >
                                    {{ mediaFilename }}
                                </p>
                            </div>
                            <div
                                class="flex justify-end gap-2 border-t border-gray-100 dark:border-gray-700 px-3 py-2"
                            >
                                <button
                                    type="button"
                                    @click="replaceMedia"
                                    class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
                                >
                                    Replace
                                </button>
                                <button
                                    type="button"
                                    @click="removeMedia"
                                    class="text-xs text-red-500 hover:underline"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>

                        <!-- Local file selected preview -->
                        <div
                            v-else-if="localFile"
                            class="mb-4 rounded-lg border border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20 p-3"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div class="flex items-center gap-2 min-w-0">
                                    <BsPaperclip
                                        class="w-4 h-4 text-blue-500 flex-shrink-0"
                                    />
                                    <span
                                        class="text-xs text-blue-700 dark:text-blue-300 truncate"
                                        >{{ localFile.name }}</span
                                    >
                                    <span class="text-xs text-blue-400"
                                        >({{
                                            formatSize(localFile.size)
                                        }})</span
                                    >
                                </div>
                                <button
                                    type="button"
                                    @click="removeMedia"
                                    class="text-blue-400 hover:text-red-500 flex-shrink-0"
                                >
                                    <BsXLg class="w-4 h-4" />
                                </button>
                            </div>
                            <!-- Upload progress -->
                            <div
                                v-if="
                                    uploadProgress > 0 && uploadProgress < 100
                                "
                                class="mt-2"
                            >
                                <div
                                    class="h-1 rounded-full bg-blue-200 dark:bg-blue-800 overflow-hidden"
                                >
                                    <div
                                        class="h-full bg-blue-500 transition-all duration-200"
                                        :style="{ width: uploadProgress + '%' }"
                                    ></div>
                                </div>
                                <p class="mt-1 text-xs text-blue-500">
                                    Uploading {{ uploadProgress }}%...
                                </p>
                            </div>
                        </div>

                        <!-- Drop zone (shown when no file) -->
                        <div
                            v-if="!form.media_url && !localFile"
                            class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 px-6 py-8 cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition-colors"
                            :class="{
                                'border-blue-400 bg-blue-50 dark:bg-blue-900/20':
                                    isDragOver,
                            }"
                            @dragover.prevent="isDragOver = true"
                            @dragleave="isDragOver = false"
                            @drop="handleDrop"
                            @click="triggerFileInput"
                        >
                            <BsCloudUpload
                                class="w-8 h-8 text-gray-400 dark:text-gray-500 mb-2"
                            />
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Drag & drop or
                                <span
                                    class="text-blue-600 dark:text-blue-400 font-medium"
                                    >browse</span
                                >
                            </p>
                            <p
                                class="mt-1 text-xs text-gray-400 dark:text-gray-500"
                            >
                                {{ mediaTypeHint }}
                            </p>
                        </div>

                        <input
                            ref="fileInputRef"
                            type="file"
                            class="hidden"
                            :accept="acceptedMimes"
                            @change="handleFileSelect"
                        />
                        <p
                            v-if="errors.media"
                            class="mt-2 text-xs text-red-500"
                        >
                            {{ errors.media }}
                        </p>
                    </div>

                    <!-- 3. Message Body -->
                    <div
                        class="border-b border-slate-300 px-6 py-5 dark:border-slate-600"
                    >
                        <!-- Section header -->
                        <div class="border border-slate-300 dark:border-slate-600 px-3 py-3 sm:px-4 rounded-lg mb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                </div>
                                <div>
                                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-200">Message Body</h2>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Write your message. Use <strong>Add Variable</strong> to insert placeholders like <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{{1}}</code> that will be replaced with contact data.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Toolbar: formatting + add variable -->
                        <div class="flex items-center flex-wrap gap-1.5 mb-2">
                            <button
                                type="button"
                                @click="wrapText('*')"
                                class="inline-flex items-center justify-center w-7 h-7 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                title="Bold"
                            >
                                <BsTypeBold class="w-3.5 h-3.5" />
                            </button>
                            <button
                                type="button"
                                @click="wrapText('_')"
                                class="inline-flex items-center justify-center w-7 h-7 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                title="Italic"
                            >
                                <BsTypeItalic class="w-3.5 h-3.5" />
                            </button>
                            <button
                                type="button"
                                @click="wrapText('~')"
                                class="inline-flex items-center justify-center w-7 h-7 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                title="Strikethrough"
                            >
                                <BsTypeStrikethrough class="w-3.5 h-3.5" />
                            </button>

                            <div
                                class="w-px h-4 bg-gray-300 dark:bg-gray-600 mx-0.5"
                            ></div>

                            <button
                                type="button"
                                :disabled="detectedVariables.length >= 20"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium border rounded transition-colors"
                                :class="detectedVariables.length >= 20
                                    ? 'border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-600 cursor-not-allowed bg-transparent'
                                    : 'border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/30 hover:bg-blue-100 dark:hover:bg-blue-900/50'"
                                @click="addVariable"
                            >
                                <BsPlus class="w-3.5 h-3.5" />
                                Add Variable
                                <span v-if="detectedVariables.length > 0" class="text-gray-400 dark:text-gray-500 font-normal">({{ detectedVariables.length }}/20)</span>
                            </button>
                        </div>

                        <textarea
                            ref="messageTextareaRef"
                            v-model="form.message_text"
                            rows="6"
                            maxlength="2000"
                            placeholder="Hello {{1}}, welcome to our service!"
                            class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 resize-none font-mono"
                            :class="{
                                'border-red-400 focus:border-red-500 focus:ring-red-500':
                                    errors.message_text,
                            }"
                        ></textarea>
                        <div class="flex items-center justify-between mt-1">
                            <p
                                v-if="errors.message_text"
                                class="text-xs text-red-500"
                            >
                                {{ errors.message_text }}
                            </p>
                            <p v-else class="text-xs text-gray-400"></p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                {{ form.message_text.length }} / 2000
                            </p>
                        </div>

                        <!-- Preview value inputs for detected variables -->
                        <div
                            v-if="detectedVariables.length"
                            class="mt-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 p-4"
                        >
                            <p
                                class="text-xs font-semibold text-blue-700 dark:text-blue-300 mb-3"
                            >
                                {{ detectedVariables.length }} variable{{
                                    detectedVariables.length > 1 ? "s" : ""
                                }}
                                detected — set preview values:
                            </p>
                            <div class="space-y-2">
                                <div
                                    v-for="(num, idx) in detectedVariables"
                                    :key="num"
                                    class="flex items-center gap-3"
                                >
                                    <code
                                        class="flex-shrink-0 text-xs px-2 py-1 rounded bg-blue-100 dark:bg-blue-800 text-blue-700 dark:text-blue-300 font-mono"
                                        >{{ varLabel(num) }}</code
                                    >
                                    <input
                                        v-model="previewValues[idx]"
                                        type="text"
                                        :placeholder="`Preview value for {{${num}}}`"
                                        class="flex-1 rounded border border-blue-200 dark:border-blue-700 bg-white dark:bg-gray-800 px-2.5 py-1.5 text-xs text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Buttons -->
                    <div class="px-6 py-5">
                        <!-- Section header -->
                        <div class="border border-slate-300 dark:border-slate-600 px-3 py-3 sm:px-4 rounded-lg mb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                                </div>
                                <div>
                                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-200">Buttons</h2>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Add Quick Reply buttons (max 13) and/or URL buttons (max 3)</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2 mb-4 flex-wrap">
                            <button
                                type="button"
                                :disabled="quickReplyCount >= 13"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border transition-colors"
                                :class="
                                    quickReplyCount >= 13
                                        ? 'border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-600 cursor-not-allowed'
                                        : 'border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/30'
                                "
                                @click="addButton('postback')"
                            >
                                <BsPlus class="w-3.5 h-3.5" />
                                Quick Reply
                                <span
                                    class="text-gray-400 dark:text-gray-500 font-normal"
                                    >({{ quickReplyCount }}/13)</span
                                >
                            </button>

                            <button
                                type="button"
                                :disabled="urlButtonCount >= 3"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border transition-colors"
                                :class="
                                    urlButtonCount >= 3
                                        ? 'border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-600 cursor-not-allowed'
                                        : 'border-green-300 dark:border-green-700 text-green-700 dark:text-green-300 hover:bg-green-50 dark:hover:bg-green-900/30'
                                "
                                @click="addButton('web_url')"
                            >
                                <BsPlus class="w-3.5 h-3.5" />
                                URL Button
                                <span
                                    class="text-gray-400 dark:text-gray-500 font-normal"
                                    >({{ urlButtonCount }}/3)</span
                                >
                            </button>
                        </div>

                        <div v-if="form.buttons.length" class="space-y-3">
                            <div
                                v-for="(btn, index) in form.buttons"
                                :key="index"
                                class="rounded-lg border p-3"
                                :class="
                                    btn.type === 'postback'
                                        ? 'border-blue-100 dark:border-blue-800 bg-blue-50/50 dark:bg-blue-900/10'
                                        : 'border-green-100 dark:border-green-800 bg-green-50/50 dark:bg-green-900/10'
                                "
                            >
                                <div
                                    class="flex items-center justify-between mb-2"
                                >
                                    <span
                                        class="text-xs font-medium px-2 py-0.5 rounded-full"
                                        :class="
                                            btn.type === 'postback'
                                                ? 'bg-blue-100 dark:bg-blue-800 text-blue-700 dark:text-blue-300'
                                                : 'bg-green-100 dark:bg-green-800 text-green-700 dark:text-green-300'
                                        "
                                    >
                                        {{
                                            btn.type === "postback"
                                                ? "Quick Reply"
                                                : "URL Button"
                                        }}
                                    </span>
                                    <button
                                        type="button"
                                        class="text-gray-400 hover:text-red-500 dark:hover:text-red-400 transition-colors"
                                        @click="removeButton(index)"
                                    >
                                        <BsXLg class="w-4 h-4" />
                                    </button>
                                </div>

                                <div class="space-y-2">
                                    <div>
                                        <label
                                            class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                            >Title
                                            <span class="text-red-500">*</span>
                                            <span class="text-gray-400"
                                                >(max 20 chars)</span
                                            ></label
                                        >
                                        <input
                                            v-model="btn.title"
                                            type="text"
                                            maxlength="20"
                                            placeholder="Button label"
                                            class="w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2.5 py-1.5 text-sm text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        />
                                    </div>
                                    <div v-if="btn.type === 'web_url'">
                                        <label
                                            class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                            >URL
                                            <span class="text-red-500"
                                                >*</span
                                            ></label
                                        >
                                        <input
                                            v-model="btn.url"
                                            type="url"
                                            placeholder="https://example.com"
                                            class="w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2.5 py-1.5 text-sm text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        />
                                    </div>
                                    <div v-if="btn.type === 'postback'">
                                        <label
                                            class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                            >Payload
                                            <span class="text-gray-400"
                                                >(auto if empty)</span
                                            ></label
                                        >
                                        <input
                                            v-model="btn.payload"
                                            type="text"
                                            :placeholder="
                                                btn.title
                                                    ? btn.title
                                                          .toUpperCase()
                                                          .replace(/\s+/g, '_')
                                                    : 'BUTTON_PAYLOAD'
                                            "
                                            class="w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2.5 py-1.5 text-sm text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p
                            v-if="errors.buttons"
                            class="mt-2 text-xs text-red-500"
                        >
                            {{ errors.buttons }}
                        </p>
                    </div>

                    <!-- Submit -->
                    <div
                        class="border-t border-slate-300 dark:border-slate-600 px-6 py-4 flex items-center justify-between gap-3"
                    >
                        <a
                            :href="listUrl"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            Cancel
                        </a>
                        <button
                            type="button"
                            :disabled="saving"
                            class="inline-flex items-center gap-2 px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
                            @click="submit"
                        >
                            <BsArrowRepeat
                                v-if="saving"
                                class="w-4 h-4 animate-spin"
                            />
                            {{
                                saving
                                    ? "Saving..."
                                    : isEditMode
                                      ? "Update Template"
                                      : "Create Template"
                            }}
                        </button>
                    </div>
                </div>

                <!-- ── Preview card ─────────────────────────────────────── -->
                <div class="xl:col-span-2">
                    <div
                        class="bg-white border border-slate-300 rounded-lg dark:bg-transparent dark:ring-slate-600 dark:border-slate-600 sticky top-4"
                    >
                        <div
                            class="border-b border-slate-300 dark:border-slate-600 px-4 py-3"
                        >
                            <div class="flex items-center gap-2">
                                <!-- Messenger icon -->
                                <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2C6.477 2 2 6.145 2 11.243c0 2.906 1.404 5.502 3.6 7.21V22l3.28-1.803A10.52 10.52 0 0012 20.485c5.523 0 10-4.145 10-9.242C22 6.145 17.523 2 12 2z" fill="url(#prev-msngr)"/>
                                    <path d="M13.197 14.3l-2.545-2.72-4.966 2.72 5.463-5.8 2.608 2.72 4.903-2.72-5.463 5.8z" fill="white"/>
                                    <defs><linearGradient id="prev-msngr" x1="2" y1="12" x2="22" y2="12" gradientUnits="userSpaceOnUse"><stop stop-color="#0099FF"/><stop offset="1" stop-color="#A033FF"/></linearGradient></defs>
                                </svg>
                                <h3
                                    class="text-sm font-semibold text-gray-700 dark:text-gray-300"
                                >
                                    Live Preview
                                </h3>
                            </div>
                        </div>
                        <div class="p-4">
                            <MessengerPreview
                                :template-data="previewData"
                                :preview-values="previewValues"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick } from 'vue';
import MessengerPreview from './MessengerPreview.vue';
import { useMessengerFile } from './composables/useMessengerFile.js';
import {
    BsCloudUpload,
    BsPaperclip,
    BsXLg,
    BsPlus,
    BsArrowRepeat,
    CaDocument,
    BsTypeBold,
    BsTypeItalic,
    BsTypeStrikethrough,
} from '@kalimahapps/vue-icons';

const { getAcceptedFileTypes, getFileTypeDescription, validateFile, formatSize } = useMessengerFile();

const props = defineProps({
    subdomain: { type: String, required: true },
    template:  { type: Object, default: null },
});

const isEditMode = computed(() => !!props.template);
const listUrl    = `/${props.subdomain}/facebook-messenger/templates`;
const csrf       = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ─── Form state ───────────────────────────────────────────────────────────────
const form = reactive({
    name:         props.template?.name         ?? '',
    content_type: props.template?.content_type ?? 'text',
    message_text: props.template?.message_text ?? '',
    media_url:    props.template?.media_url    ?? '',
    buttons: Array.isArray(props.template?.buttons)
        ? props.template.buttons.map(b => ({ ...b }))
        : [],
});

// ─── Errors / state ───────────────────────────────────────────────────────────
const errors  = reactive({});
const saving  = ref(false);

// ─── Media upload ─────────────────────────────────────────────────────────────
const fileInputRef      = ref(null);
const localFile         = ref(null);
const isDragOver        = ref(false);
const uploadProgress    = ref(0);
// Tracks the last confirmed server-side URL so we can send the right old_media_url
// even if the user replaces a file multiple times before hitting Save.
const uploadedServerUrl = ref(props.template?.media_url ?? null);

const contentTypeLabel = computed(() => ({
    image: 'Image', video: 'Video', document: 'Document',
}[form.content_type] ?? ''));

const mediaTypeHint  = computed(() => getFileTypeDescription(form.content_type));
const acceptedMimes  = computed(() => getAcceptedFileTypes(form.content_type));

const mediaFilename = computed(() => {
    if (!form.media_url) return '';
    return form.media_url.split('/').pop() ?? 'file';
});

// Icon to show for document in existing file preview — static reference, no computed needed
const mediaIcon = CaDocument;

function onContentTypeChange() {
    localFile.value      = null;
    uploadProgress.value = 0;
    if (form.content_type === 'text') form.media_url = '';
    delete errors.media;
}

function triggerFileInput() { fileInputRef.value?.click(); }
function replaceMedia()     { triggerFileInput(); }
function removeMedia() {
    localFile.value      = null;
    form.media_url       = '';
    uploadProgress.value = 0;
    if (fileInputRef.value) fileInputRef.value.value = '';
    delete errors.media;
}

function handleDrop(e) {
    e.preventDefault();
    isDragOver.value = false;
    const f = e.dataTransfer.files[0];
    if (f) processFile(f);
}

function handleFileSelect(e) {
    const f = e.target.files[0];
    if (f) processFile(f);
}

function processFile(file) {
    delete errors.media;
    const err = validateFile(file, form.content_type);
    if (err) { errors.media = err; return; }
    localFile.value = file;
    // Immediate local preview via object URL
    form.media_url = URL.createObjectURL(file);
}

async function uploadFileNow() {
    if (!localFile.value) return true;

    uploadProgress.value = 0;
    const fd = new FormData();
    fd.append('file', localFile.value);
    fd.append('type', form.content_type);

    // Pass the last known server URL so the controller can delete it on replace.
    // We track uploadedServerUrl (not props.template.media_url) so that if the user
    // replaces a file multiple times before saving, each replacement cleans up the
    // previous upload rather than leaking files on disk.
    if (uploadedServerUrl.value) {
        fd.append('old_media_url', uploadedServerUrl.value);
    }

    const timer = setInterval(() => {
        if (uploadProgress.value < 85) uploadProgress.value += 10;
    }, 120);

    try {
        const res = await fetch(`/${props.subdomain}/facebook-messenger/upload-media`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf() },
            body: fd,
        });
        clearInterval(timer);
        uploadProgress.value = 100;

        const data = await res.json();
        if (!res.ok || !data.success) {
            errors.media = data.message ?? 'Upload failed.';
            uploadProgress.value = 0;
            return false;
        }
        form.media_url       = data.file_url;
        uploadedServerUrl.value = data.file_url;  // remember for next replacement
        localFile.value     = null;
        return true;
    } catch {
        clearInterval(timer);
        uploadProgress.value = 0;
        errors.media = 'Upload failed. Please try again.';
        return false;
    }
}

// ─── Variable system ({{1}}, {{2}}, ...) ─────────────────────────────────────
const messageTextareaRef = ref(null);
const previewValues      = ref([]);

const detectedVariables = computed(() => {
    const matches = form.message_text.match(/\{\{(\d+)\}\}/g);
    if (!matches) return [];
    // Use Set for O(n) deduplication instead of indexOf O(n²)
    return [...new Set(matches.map(m => m.replace(/\{\{|\}\}/g, '')))]
        .sort((a, b) => parseInt(a) - parseInt(b));
});

// Mutate in-place so v-model bindings on existing inputs are preserved.
// Newly detected variables default to 'Value N' so the preview substitutes immediately.
watch(detectedVariables, (vars) => {
    const n = vars.length;
    if (previewValues.value.length > n) {
        previewValues.value.splice(n);
    }
    while (previewValues.value.length < n) {
        previewValues.value.push(`Value ${previewValues.value.length + 1}`);
    }
}, { immediate: true });

// Returns "{{n}}" without literal {{ in template source (avoids Vue parser confusion)
function varLabel(n) { return '{' + '{' + n + '}' + '}'; }

const MAX_VARIABLES = 20;

function addVariable() {
    if (detectedVariables.value.length >= MAX_VARIABLES) return;
    const el  = messageTextareaRef.value;
    const num = detectedVariables.value.length + 1;
    const tag = `{{${num}}}`;

    if (!el) { form.message_text += tag; return; }
    const start = el.selectionStart;
    const end   = el.selectionEnd;
    form.message_text = form.message_text.slice(0, start) + tag + form.message_text.slice(end);
    nextTick(() => {
        const pos = start + tag.length;
        el.focus();
        el.setSelectionRange(pos, pos);
    });
}

// ─── Text formatting ──────────────────────────────────────────────────────────
function wrapText(marker) {
    const el = messageTextareaRef.value;
    if (!el) return;
    const start    = el.selectionStart;
    const end      = el.selectionEnd;
    const selected = form.message_text.slice(start, end);
    const wrapped  = `${marker}${selected}${marker}`;
    form.message_text = form.message_text.slice(0, start) + wrapped + form.message_text.slice(end);
    nextTick(() => {
        el.focus();
        el.setSelectionRange(start + marker.length, start + marker.length + selected.length);
    });
}

// ─── Buttons ──────────────────────────────────────────────────────────────────
const quickReplyCount = computed(() => form.buttons.filter(b => b.type === 'postback').length);
const urlButtonCount  = computed(() => form.buttons.filter(b => b.type === 'web_url').length);

function addButton(type) {
    if (type === 'postback' && quickReplyCount.value >= 13) return;
    if (type === 'web_url'  && urlButtonCount.value  >= 3)  return;
    form.buttons.push(type === 'postback'
        ? { type: 'postback', title: '', payload: '' }
        : { type: 'web_url',  title: '', url: '' });
}

function removeButton(index) { form.buttons.splice(index, 1); }

// ─── Preview data ─────────────────────────────────────────────────────────────
const previewData = computed(() => ({
    content_type: form.content_type,
    message_text: form.message_text,
    media_url:    form.media_url,
    buttons:      form.buttons,
}));

// ─── Validation ───────────────────────────────────────────────────────────────
function validate() {
    Object.keys(errors).forEach(k => delete errors[k]);
    let ok = true;

    if (!form.name.trim())        { errors.name = 'Template name is required.';           ok = false; }
    if (!form.content_type)       { errors.content_type = 'Content type is required.';    ok = false; }
    if (form.content_type === 'text' && !form.message_text.trim()) {
        errors.message_text = 'Message text is required for text templates.'; ok = false;
    }
    if (form.content_type !== 'text' && !form.media_url && !localFile.value) {
        errors.media = `A ${form.content_type} file is required for this template type.`; ok = false;
    }
    for (let i = 0; i < form.buttons.length; i++) {
        const b = form.buttons[i];
        if (!b.title.trim()) { errors.buttons = `Button ${i + 1} title is required.`; ok = false; break; }
        if (b.type === 'web_url' && !b.url.trim()) { errors.buttons = `Button ${i + 1} URL is required.`; ok = false; break; }
    }
    return ok;
}

// ─── Submit ───────────────────────────────────────────────────────────────────
async function submit() {
    if (!validate()) return;

    saving.value = true;

    if (localFile.value) {
        const uploaded = await uploadFileNow();
        if (!uploaded) { saving.value = false; return; }
    }

    const buttons = form.buttons.map(btn => ({
        ...btn,
        ...(btn.type === 'postback' ? {
            payload: btn.payload.trim() || btn.title.toUpperCase().replace(/\s+/g, '_'),
        } : {}),
    }));

    const payload = {
        name:         form.name.trim(),
        content_type: form.content_type,
        message_text: form.message_text,
        media_url:    form.media_url || null,
        buttons,
    };

    const url = isEditMode.value
        ? `/${props.subdomain}/facebook-messenger/template/${props.template.id}/update`
        : `/${props.subdomain}/facebook-messenger/template`;

    try {
        const res  = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (!res.ok || !data.success) {
            if (data.errors) Object.assign(errors, data.errors);
            showNotification(data.message ?? 'Something went wrong.', 'danger');
            return;
        }
        if (data.redirect_url) window.location.href = data.redirect_url;
    } catch {
        showNotification('Network error. Please try again.', 'danger');
    } finally {
        saving.value = false;
    }
}
</script>
