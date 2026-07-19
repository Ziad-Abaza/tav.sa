<div class="relative">
    <x-slot:title>
        {{ t('tenants') }}
    </x-slot:title>

    <x-breadcrumb :items="[
        ['label' => t('dashboard'), 'route' => route('admin.dashboard')],
        ['label' => t('tenants')],
    ]" />

    <div class="flex justify-start mb-3 lg:px-0 items-center gap-2">
        @if(checkPermission('admin.tenants.create'))
        <a href="{{ route('admin.tenants.save') }}">
            <x-button.primary>
                <x-heroicon-m-plus class="w-4 h-4 mr-1" />{{ t('new_tenant') }}
            </x-button.primary>
        </a>
        @endif
    </div>

    <div class="mt-8 lg:mt-0">
        <div wire:ignore>
            <div id="admin-tenant-datatable"></div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-data="{ show: @entangle('confirmingDeletion') }"
         x-on:close.stop="show = false"
         x-on:keydown.escape.window="show = false"
         x-show="show"
         class="jetstream-modal fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50 flex items-center justify-center backdrop-blur-sm bg-gradient-to-br from-black/30 to-black/60"
         style="display: none;">

        <div x-show="show" class="fixed inset-0 transform transition-all" x-on:click="show = false"
             x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div>
        </div>

        <div x-show="show"
             class="mb-6 bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:max-w-2xl sm:mx-auto"
             x-trap.inert.noscroll="show"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

            <!-- Modal Header -->
            <div class="w-full flex p-5 flex-col sm:flex-row">
                <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-danger-100 sm:mx-0 sm:size-10">
                    <x-heroicon-o-exclamation-triangle class="w-7 h-7 text-danger-600" />
                </div>

                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                    <h3 class="text-base font-semibold text-slate-700 dark:text-slate-200">
                        {{ t('delete_tenant_title') }}
                    </h3>

                    @if(! $tenantHasSubscription)
                        {{-- Case 1: No subscription — instant delete with warning --}}
                        <div class="mt-3 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                            <div class="flex items-start gap-2">
                                <x-heroicon-o-exclamation-circle class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-sm font-semibold text-red-800 dark:text-red-300">
                                        {{ t('no_subscription_instant_delete_warning') }}
                                    </p>
                                    <p class="text-xs text-red-600 dark:text-red-400 mt-1">
                                        {{ t('no_subscription_instant_delete_description') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Case 2: Has subscription — show 2 options --}}
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            {{ t('tenant_has_subscription_delete_prompt') }}
                        </p>

                        <div class="mt-3 space-y-3">
                            {{-- Option 1: Delete after subscription ends (default) --}}
                            <label class="flex items-start gap-3 p-3 border-2 rounded-lg cursor-pointer transition-all
                                {{ $deleteMode === 'after_subscription' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-primary-300' }}">
                                <input type="radio" wire:model.live="deleteMode" value="after_subscription"
                                    class="mt-0.5 text-primary-600 focus:ring-primary-500" />
                                <div>
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200 flex items-center gap-1">
                                        <x-heroicon-o-clock class="w-4 h-4 text-primary-500" />
                                        {{ t('delete_after_subscription_ends') }}
                                    </span>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                        {{ t('delete_after_subscription_ends_description') }}
                                    </p>
                                </div>
                            </label>

                            {{-- Option 2: Delete immediately --}}
                            <label class="flex items-start gap-3 p-3 border-2 rounded-lg cursor-pointer transition-all
                                {{ $deleteMode === 'instant' ? 'border-red-500 bg-red-50 dark:bg-red-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-red-300' }}">
                                <input type="radio" wire:model.live="deleteMode" value="instant"
                                    class="mt-0.5 text-red-600 focus:ring-red-500" />
                                <div>
                                    <span class="text-sm font-medium text-red-700 dark:text-red-400 flex items-center gap-1">
                                        <x-heroicon-o-bolt class="w-4 h-4 text-red-500" />
                                        {{ t('delete_immediately') }}
                                    </span>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                        {{ t('delete_immediately_description') }}
                                    </p>
                                </div>
                            </label>

                            {{-- Extra warning shown only when instant mode is selected --}}
                            @if($deleteMode === 'instant')
                                <div class="flex items-start gap-2 p-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded">
                                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                                    <p class="text-xs text-amber-700 dark:text-amber-300 font-medium">
                                        {{ t('instant_delete_subscription_warning') }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="border-neutral-500/30 flex justify-end items-center gap-3 bg-gray-100 dark:bg-gray-700 px-4 py-3">
                <x-button.cancel-button wire:click="$set('confirmingDeletion', false)">
                    {{ t('cancel') }}
                </x-button.cancel-button>

                @if(! $tenantHasSubscription || $deleteMode === 'instant')
                    <x-button.delete-button wire:click="delete">
                        {{ t('delete_permanently') }}
                    </x-button.delete-button>
                @else
                    <x-button.delete-button wire:click="delete">
                        {{ t('mark_tenant_for_deletion') }}
                    </x-button.delete-button>
                @endif
            </div>
        </div>
    </div>
</div>
