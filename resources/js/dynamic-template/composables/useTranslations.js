import { ref, computed } from 'vue';

/**
 * Vue Translation Composable
 * Integrates with existing Laravel translation system (t() function)
 * Works with both tenant and admin contexts
 */
export function useTranslations() {
	// Get translations and locale from window variables set by Laravel
	const translations = ref(window.translations || {});
	const currentLocale = ref(window.appLocale || 'en');
	const isTenant = ref(window.isTenant || false);

	/**
	 * Translation function - mirrors PHP t() function behavior
	 * @param {string} key - Translation key
	 * @param {object} replacements - Key-value pairs for :placeholder replacement
	 * @param {string|null} locale - Optional locale override
	 * @returns {string} - Translated text
	 */
	const t = (key, replacements = {}, locale = null) => {
		const targetLocale = locale || currentLocale.value;
		let translation = translations.value[key] || key;

		// Handle :placeholder replacements (same as PHP t() function)
		Object.entries(replacements).forEach(([placeholder, value]) => {
			translation = translation.replace(`:${placeholder}`, value);
		});

		return translation;
	};

	/**
	 * Update translations (for dynamic language switching)
	 * @param {object} newTranslations
	 */
	const setTranslations = (newTranslations) => {
		translations.value = { ...newTranslations };
		window.translations = newTranslations;
	};

	/**
	 * Set current locale
	 * @param {string} locale
	 */
	const setLocale = (locale) => {
		currentLocale.value = locale;
		window.appLocale = locale;
	};

	/**
	 * Load translations for a specific locale
	 * @param {string} locale
	 * @returns {Promise}
	 */
	const loadTranslations = async (locale) => {
		try {
			const subdomain = window.subdomain || 'admin';
			const endpoint = `/${subdomain}/api/translations/${locale}`;

			const response = await fetch(endpoint, {
				method: 'GET',
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
					'Accept': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
				},
			});

			if (!response.ok) {
				throw new Error(`Failed to load translations for ${locale}`);
			}

			const data = await response.json();
			setTranslations(data.translations);
			setLocale(data.locale);

			return data;
		} catch (error) {
			console.error('Error loading translations:', error);
			throw error;
		}
	};

	/**
	 * Check if a translation key exists
	 * @param {string} key
	 * @returns {boolean}
	 */
	const hasTranslation = (key) => {
		return translations.value.hasOwnProperty(key);
	};

	/**
	 * Get all available translations
	 * @returns {object}
	 */
	const getAllTranslations = () => {
		return translations.value;
	};

	return {
		t,
		currentLocale: computed(() => currentLocale.value),
		isTenant: computed(() => isTenant.value),
		translations: computed(() => translations.value),
		setTranslations,
		setLocale,
		loadTranslations,
		hasTranslation,
		getAllTranslations
	};
}
