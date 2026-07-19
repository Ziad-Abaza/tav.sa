<x-app-layout>
    <x-slot:title>
        {{ $campaign->name }} - {{ t('campaign_details') }}
    </x-slot:title>

    <x-breadcrumb :items="[
        ['label' => t('dashboard'), 'route' => tenant_route('tenant.dashboard')],
        ['label' => t('fb_messenger_campaigns'), 'route' => tenant_route('tenant.facebook-messenger.campaigns')],
        ['label' => t('campaign_details')],
    ]" />

    <section class="bg-gray-50 dark:bg-slate-800">

        {{-- Action Buttons --}}
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">{{ $campaign->name }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1.5">
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $campaign->total_count }}
                        {{ t('contacts') }}</span>
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                @if ($campaign->pause_campaign)
                    <a href="{{ tenant_route('tenant.facebook-messenger.campaigns') }}"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-slate-700 dark:text-gray-300 dark:border-slate-600 dark:hover:bg-slate-600">
                        <x-heroicon-o-arrow-left class="h-4 w-4 mr-2" />
                        {{ t('back_to_campaigns') }}
                    </a>
                @endif

                @if (checkPermission('tenant.facebook_messenger.campaigns.create'))
                    <a href="{{ tenant_route('tenant.facebook-messenger.campaign.create') }}"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600">
                        <x-heroicon-o-plus class="h-5 w-5 mr-2" />
                        {{ t('create_new_campaign') }}
                    </a>
                @endif
            </div>
        </div>

        {{-- Campaign Info Panel --}}
        @php
            $pendingCount = $campaign->getPendingCount();
            $sentCount = $campaign->getSentCount();
            $failedCount = $campaign->getFailedCount();
            $totalCount = $campaign->total_count ?: 1;

            $sentPercent = round(($sentCount / $totalCount) * 100, 1);
            $failedPercent = round(($failedCount / $totalCount) * 100, 1);
            $pendingPercent = round(($pendingCount / $totalCount) * 100, 1);

            $campaignStatus = $campaign->getStatusLabel();
            $statusColor = $campaign->getStatusColor();
        @endphp

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6 dark:bg-slate-800 dark:border-slate-700">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">

                <!-- Status -->
                <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0">
                        <div class="p-3 rounded-full {{ $statusColor === 'green' ? 'bg-green-100 dark:bg-green-900/30' : ($statusColor === 'yellow' ? 'bg-yellow-100 dark:bg-yellow-900/30' : ($statusColor === 'red' ? 'bg-red-100 dark:bg-red-900/30' : 'bg-primary-100 dark:bg-primary-900/30')) }}">
                            @if ($campaignStatus === 'sent')
                                <x-heroicon-o-check-circle class="h-6 w-6 text-green-600 dark:text-green-400" />
                            @elseif ($campaignStatus === 'in_progress' || $campaignStatus === 'pending')
                                <x-heroicon-o-clock class="h-6 w-6 text-yellow-600 dark:text-yellow-400" />
                            @elseif ($campaignStatus === 'paused')
                                <x-heroicon-o-pause class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                            @else
                                <x-heroicon-o-question-mark-circle class="h-6 w-6 text-gray-600 dark:text-gray-400" />
                            @endif
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('status') }}</p>
                        <div class="mt-1">
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor === 'green' ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : ($statusColor === 'yellow' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300' : ($statusColor === 'red' ? 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' : 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300')) }}">
                                {{ t($campaignStatus) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Template -->
                <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0">
                        <div class="p-3 rounded-full bg-primary-100 dark:bg-primary-900/30">
                            <x-heroicon-o-document-text class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('template') }}</p>
                        <p class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-200">
                            {{ $campaign->fbTemplate?->name ?? t('no_template') }}
                        </p>
                    </div>
                </div>

                <!-- Schedule -->
                <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0">
                        <div class="p-3 rounded-full bg-primary-100 dark:bg-primary-900/30">
                            <x-heroicon-o-calendar class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('scheduled_for') }}</p>
                        <p class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-200">
                            @if ($campaign->send_now)
                                {{ t('immediate') }}
                            @elseif ($campaign->scheduled_send_time)
                                {{ $campaign->scheduled_send_time->format('M d, Y H:i') }}
                            @else
                                {{ t('not_scheduled') }}
                            @endif
                        </p>
                    </div>
                </div>

                <!-- Created -->
                <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0">
                        <div class="p-3 rounded-full bg-gray-100 dark:bg-gray-900/30">
                            <x-heroicon-o-clock class="h-6 w-6 text-gray-600 dark:text-gray-400" />
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('created') }}</p>
                        <p class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-200">
                            {{ $campaign->created_at->format('M d, Y H:i') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Sent -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 dark:bg-slate-800 dark:border-slate-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('delivered') }}</p>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ $sentCount }}</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">/ {{ $totalCount }}</span>
                        </div>
                    </div>
                    <div class="p-3 bg-green-100 rounded-full dark:bg-green-900/30">
                        <x-heroicon-o-check-circle class="h-5 w-5 text-green-600 dark:text-green-400" />
                    </div>
                </div>
                <div class="mt-3">
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ $sentPercent }}%</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ t('rate') }}</span>
                    </div>
                </div>
            </div>

            <!-- Pending -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 dark:bg-slate-800 dark:border-slate-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('pending') }}</p>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ $pendingCount }}</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">/ {{ $totalCount }}</span>
                        </div>
                    </div>
                    <div class="p-3 bg-yellow-100 rounded-full dark:bg-yellow-900/30">
                        <x-heroicon-o-clock class="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                    </div>
                </div>
                <div class="mt-3">
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ $pendingPercent }}%</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ t('rate') }}</span>
                    </div>
                </div>
            </div>

            <!-- Failed -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 dark:bg-slate-800 dark:border-slate-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('failed') }}</p>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ $failedCount }}</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">/ {{ $totalCount }}</span>
                        </div>
                    </div>
                    <div class="p-3 bg-red-100 rounded-full dark:bg-red-900/30">
                        <x-heroicon-o-x-circle class="h-5 w-5 text-red-600 dark:text-red-400" />
                    </div>
                </div>
                <div class="mt-3">
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ $failedPercent }}%</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ t('rate') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Details Table --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 dark:bg-slate-800">
            <div class="border-b border-gray-200 dark:border-slate-700 px-6 py-4">
                <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200">{{ t('recipients') }}</h3>
            </div>
            <div class="p-6">
                <div id="fb-campaign-details-datatable" data-subdomain="{{ $subdomain }}"
                    data-campaign-id="{{ $campaign->id }}">
                </div>
            </div>
        </div>

    </section>
</x-app-layout>
