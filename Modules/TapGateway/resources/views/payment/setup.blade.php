<x-app-layout>
    <x-slot:title>
        {{ t('setup_tap_payment') ?? 'Setup Tap Payment' }}
    </x-slot:title>

    <div class="max-w-5xl mx-auto">
        <x-card>
            <!-- Enhanced Header Section -->
            <x-slot:header>
                <div class="flex items-center space-x-3">
                    <div class="w-6 h-6 sm:w-10 sm:h-10 bg-primary-100 rounded-full flex items-center justify-center">
                        <x-heroicon-o-credit-card class="w-6 h-6 text-primary-600" />
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-300">
                            {{ t('setup_tap_payment') ?? 'Setup Tap Payment' }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-300">
                            {{ t('configure_auto_billing_description') ?? 'Configure automatic billing for your subscription' }}
                        </p>
                    </div>
                </div>
            </x-slot:header>

            <x-slot:content>
                @if(isset($plan))
                <!-- Plan Information Panel -->
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden shadow sm:rounded-lg"
                    x-data="{ expanded: true }">
                    <div class="flex items-center justify-between px-4 py-5 sm:px-6 bg-primary-50 dark:bg-slate-700 cursor-pointer"
                        @click="expanded = !expanded">
                        <div class="flex items-center">
                            <x-heroicon-s-rectangle-stack class="h-6 w-6 text-gray-600 dark:text-gray-400 mr-3" />
                            <h2 class="text-lg font-medium text-gray-900 dark:text-slate-200">
                                {{ t('selected_plan') ?? 'Selected Plan' }}
                            </h2>
                        </div>
                        <div class="flex items-center">
                            <span class="mr-3 text-sm font-semibold text-primary-600 dark:text-slate-200">
                                {{ formatMoney($plan->price, $plan->currency) }}
                                @if($plan->interval)
                                    <span class="text-xs text-gray-500 dark:text-gray-400">/ {{ t($plan->interval) }}</span>
                                @endif
                            </span>
                            <x-heroicon-s-chevron-down x-show="!expanded" class="h-5 w-5 text-gray-500" />
                            <x-heroicon-s-chevron-up x-show="expanded" class="h-5 w-5 text-gray-500" />
                        </div>
                    </div>

                    <div x-show="expanded" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0">
                        <dl class="divide-y divide-gray-200 dark:divide-slate-700">
                            <div class="px-4 py-4 sm:px-6 grid grid-cols-2">
                                <dt class="text-sm font-medium text-gray-500 text-left">{{ t('plan_name') ?? 'Plan Name' }}</dt>
                                <dd class="text-sm text-gray-900 dark:text-slate-200 text-right font-semibold">{{ $plan->name }}</dd>
                            </div>
                            <div class="px-4 py-4 sm:px-6 sm:grid sm:grid-cols-2 bg-gray-50 dark:bg-slate-700">
                                <dt class="text-lg font-semibold text-gray-900 dark:text-slate-200 text-left">{{ t('price') ?? 'Price' }}</dt>
                                <dd class="mt-1 text-lg font-bold text-primary-600 dark:text-primary-400 sm:mt-0 text-right">
                                    {{ formatMoney($plan->price, $plan->currency) }}
                                    @if($plan->interval)
                                        <span class="text-sm text-gray-500 dark:text-gray-400">/ {{ t($plan->interval) }}</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
                @endif

                <!-- Setup Form Section -->
                <div class="mt-8">
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow sm:rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-slate-200 mb-4">
                                {{ t('setup_auto_billing') ?? 'Setup Auto Billing' }}
                            </h3>

                            <div x-data="{
                                processing: false,
                                submitSetup() {
                                    this.processing = true;
                                    document.getElementById('tap-setup-form').submit();
                                }
                            }">
                                <form id="tap-setup-form" method="GET" action="{{ $returnUrl ?? tenant_route('tenant.billing') }}">
                                    <!-- No form data needed - redirecting to return URL or billing page -->
                                </form>

                                    <!-- Customer Information Section -->
                                    <div class="space-y-6">
                                        <div>
                                            <h4 class="text-md font-semibold text-gray-900 dark:text-slate-200 mb-4 flex items-center">
                                                <x-heroicon-o-user class="h-5 w-5 text-gray-500 mr-2" />
                                                {{ t('customer_information') ?? 'Customer Information' }}
                                            </h4>
                                            
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        {{ t('first_name') ?? 'First Name' }}
                                                    </label>
                                                    <input id="first_name" name="first_name" type="text" 
                                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                        value="{{ old('first_name', auth()->user()->first_name ?? '') }}" required />
                                                    @error('first_name')
                                                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                                
                                                <div>
                                                    <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        {{ t('last_name') ?? 'Last Name' }}
                                                    </label>
                                                    <input id="last_name" name="last_name" type="text" 
                                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                        value="{{ old('last_name', auth()->user()->last_name ?? '') }}" required />
                                                    @error('last_name')
                                                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>
                                            
                                            <div class="mt-4">
                                                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    {{ t('email_address') ?? 'Email Address' }}
                                                </label>
                                                <input id="email" name="email" type="email" 
                                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                    value="{{ old('email', auth()->user()->email ?? '') }}" required />
                                                @error('email')
                                                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            
                                            <div class="mt-4">
                                                <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    {{ t('phone_number') ?? 'Phone Number' }}
                                                </label>
                                                <input id="phone" name="phone" type="tel" 
                                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                    value="{{ old('phone', auth()->user()->phone ?? '') }}" />
                                                @error('phone')
                                                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- Billing Information Section -->
                                        <div class="pt-6 border-t border-gray-200 dark:border-gray-700">
                                            <h4 class="text-md font-semibold text-gray-900 dark:text-slate-200 mb-4 flex items-center">
                                                <x-heroicon-o-home class="h-5 w-5 text-gray-500 mr-2" />
                                                {{ t('billing_information') ?? 'Billing Information' }}
                                            </h4>
                                            
                                            <div class="space-y-4">
                                                <div>
                                                    <label for="billing_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        {{ t('address') ?? 'Address' }}
                                                    </label>
                                                    <input id="billing_address" name="billing_address" type="text" 
                                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                        value="{{ old('billing_address') }}" />
                                                    @error('billing_address')
                                                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                                
                                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                                    <div>
                                                        <label for="billing_city" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                            {{ t('city') ?? 'City' }}
                                                        </label>
                                                        <input id="billing_city" name="billing_city" type="text" 
                                                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                            value="{{ old('billing_city') }}" />
                                                        @error('billing_city')
                                                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                    
                                                    <div>
                                                        <label for="billing_state" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                            {{ t('state_province') ?? 'State/Province' }}
                                                        </label>
                                                        <input id="billing_state" name="billing_state" type="text" 
                                                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                            value="{{ old('billing_state') }}" />
                                                        @error('billing_state')
                                                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                    
                                                    <div>
                                                        <label for="billing_postal_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                            {{ t('postal_code') ?? 'Postal Code' }}
                                                        </label>
                                                        <input id="billing_postal_code" name="billing_postal_code" type="text" 
                                                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                            value="{{ old('billing_postal_code') }}" />
                                                        @error('billing_postal_code')
                                                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                </div>
                                                
                                                <div>
                                                    <label for="billing_country" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        {{ t('country') ?? 'Country' }}
                                                    </label>
                                                    <select id="billing_country" name="billing_country" 
                                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                        <option value="">{{ t('select_country') ?? 'Select Country' }}</option>
                                                        <option value="KW" {{ old('billing_country') == 'KW' ? 'selected' : '' }}>Kuwait</option>
                                                        <option value="SA" {{ old('billing_country') == 'SA' ? 'selected' : '' }}>Saudi Arabia</option>
                                                        <option value="AE" {{ old('billing_country') == 'AE' ? 'selected' : '' }}>United Arab Emirates</option>
                                                        <option value="BH" {{ old('billing_country') == 'BH' ? 'selected' : '' }}>Bahrain</option>
                                                        <option value="QA" {{ old('billing_country') == 'QA' ? 'selected' : '' }}>Qatar</option>
                                                        <option value="OM" {{ old('billing_country') == 'OM' ? 'selected' : '' }}>Oman</option>
                                                        <option value="JO" {{ old('billing_country') == 'JO' ? 'selected' : '' }}>Jordan</option>
                                                        <option value="EG" {{ old('billing_country') == 'EG' ? 'selected' : '' }}>Egypt</option>
                                                    </select>
                                                    @error('billing_country')
                                                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Terms and Conditions -->
                                        <div class="pt-6 border-t border-gray-200 dark:border-gray-700">
                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input id="agree_terms" name="agree_terms" type="checkbox" 
                                                        class="focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded"
                                                        required>
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="agree_terms" class="font-medium text-gray-700 dark:text-gray-300">
                                                        {{ t('agree_to_terms') ?? 'I agree to the terms and conditions' }}
                                                    </label>
                                                    <p class="text-gray-500 dark:text-gray-400">
                                                        {{ t('auto_billing_agreement_description') ?? 'By checking this box, you agree to automatic billing for your subscription.' }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="pt-6 border-t border-gray-200 dark:border-gray-700">
                                            <div class="flex flex-col sm:flex-row gap-4">
                                                <button type="button" @click="submitSetup()" 
                                                    x-bind:disabled="processing"
                                                    class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                                    <svg x-show="processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" 
                                                         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    <span x-show="!processing">
                                                        <x-heroicon-o-bolt class="w-5 h-5 mr-2" />
                                                        {{ t('setup_auto_billing') ?? 'Setup Auto Billing' }}
                                                    </span>
                                                    <span x-show="processing">
                                                        {{ t('setting_up') ?? 'Setting up...' }}
                                                    </span>
                                                </button>
                                                
                                                <button type="button" onclick="history.back()" 
                                                    class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-gray-300 dark:border-gray-600 text-base font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                                    {{ t('cancel') ?? 'Cancel' }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Notice -->
                <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="flex">
                        <x-heroicon-o-shield-check class="h-5 w-5 text-blue-500 mr-3 mt-0.5" />
                        <div class="text-sm text-blue-600 dark:text-blue-300">
                            <p class="font-medium">{{ t('secure_setup') ?? 'Secure Setup' }}</p>
                            <p>{{ t('tap_secure_setup_description') ?? 'Your information is processed securely by Tap Payments. We use industry-standard encryption to protect your data.' }}</p>
                        </div>
                    </div>
                </div>
            </x-slot:content>
        </x-card>
    </div>
</x-app-layout>