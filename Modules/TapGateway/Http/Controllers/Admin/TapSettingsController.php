<?php

namespace Modules\TapGateway\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Rules\PurifiedInput;
use App\Settings\PaymentSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\TapGateway\Services\TapGatewayService;
use Nwidart\Modules\Facades\Module;

class TapSettingsController extends Controller
{
    protected PaymentSettings $settings;

    protected TapGatewayService $tapService;

    public function __construct(PaymentSettings $settings, TapGatewayService $tapService)
    {
        $this->settings = $settings;
        $this->tapService = $tapService;
    }

    /**
     * Check if TapGateway module is active
     */
    private function isModuleActive(): bool
    {
        return Module::find('TapGateway')?->isEnabled() ?? false;
    }

    /**
     * Display the Tap payment settings page
     */
    public function index()
    {
        if (! $this->isModuleActive()) {
            session()->flash('notification', [
                'type' => 'danger',
                'message' => 'TapGateway module is not active',
            ]);

            return redirect(route('admin.payment-settings'));
        }

        if (! checkPermission('admin.payment_settings.view')) {
            session()->flash('notification', [
                'type' => 'danger',
                'message' => t('access_denied_note'),
            ]);

            return redirect(route('admin.dashboard'));
        }

        return view('TapGateway::admin.settings.tap', [
            'settings' => $this->settings,
        ]);
    }

    /**
     * Update the payment settings
     */
    public function update(Request $request)
    {
        if (! $this->isModuleActive()) {
            session()->flash('notification', [
                'type' => 'danger',
                'message' => 'TapGateway module is not active',
            ]);

            return redirect(route('admin.payment-settings'));
        }

        if (! checkPermission('admin.payment_settings.edit')) {
            session()->flash('notification', [
                'type' => 'danger',
                'message' => t('access_denied_note'),
            ]);

            return redirect(route('admin.payment-settings'));
        }

        $validated = $request->validate([
            'tap_enabled' => 'string|in:on,off',
            'tap_secret_key' => [
                'required_if:tap_enabled,on',
                'nullable',
                'string',
                'max:255',
                new PurifiedInput(t('sql_injection_error')),
            ],
            'tap_public_key' => [
                'required_if:tap_enabled,on',
                'nullable',
                'string',
                'max:255',
                new PurifiedInput(t('sql_injection_error')),
            ],
            'tap_sandbox_mode' => 'string|in:on,off',
        ]);

        try {
            $this->settings->tap_enabled = $request->tap_enabled === 'on' ? true : false;
            $this->settings->tap_secret_key = $request->tap_secret_key ?? '';
            $this->settings->tap_public_key = $request->tap_public_key ?? '';
            $this->settings->tap_sandbox_mode = $request->tap_sandbox_mode === 'on' ? true : false;
            $this->settings->save();

            session()->flash('notification', [
                'type' => 'success',
                'message' => t('settings_saved_successfully'),
            ]);
        } catch (\Exception $e) {
            session()->flash('notification', [
                'type' => 'danger',
                'message' => t('something_went_wrong').': '.$e->getMessage(),
            ]);
        }

        return redirect()->back();
    }

    /**
     * Test Tap connection
     */
    public function testConnection(Request $request)
    {

        // Check if module is active
        if (! $this->isModuleActive()) {
            return response()->json([
                'success' => false,
                'message' => 'TapGateway module is not active',
            ], 403);
        }

        if (! checkPermission('admin.payment_settings.edit')) {
            return response()->json([
                'success' => false,
                'message' => t('access_denied_note'),
            ], 403);
        }

        try {
            $validated = $request->validate([
                'secret_key' => 'required|string',
                'sandbox_mode' => 'required|boolean',
            ]);

            // Update service with test credentials
            $this->tapService = new TapGatewayService(
                $request->secret_key,
                $request->sandbox_mode
            );

            $result = $this->tapService->testConnection();

            return response()->json([
                'success' => true,
                'message' => t('connection_successful'),
            ]);

        } catch (\Exception $e) {
            Log::error('Tap Payment Test Connection Error: '.$e->getMessage(), [
                'request' => $request->all(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => t('connection_failed').': '.$e->getMessage(),
            ], 500);
        }
    }
}
