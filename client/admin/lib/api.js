/**
 * Thin wrapper over the plugin's REST endpoints.
 *
 * `wp-api-fetch` is enqueued as a dependency of the admin bundle, so the root
 * URL and the REST nonce middleware are already registered by WordPress.
 */

import apiFetch from '@wordpress/api-fetch';

const NAMESPACE = '/flutterwave/v1';

export const fetchSettings = () =>
	apiFetch( { path: `${ NAMESPACE }/settings` } );

export const saveSettings = ( data ) =>
	apiFetch( {
		path: `${ NAMESPACE }/settings`,
		method: 'POST',
		data,
	} );

export const completeOnboarding = ( data = {} ) =>
	apiFetch( {
		path: `${ NAMESPACE }/onboarding/complete`,
		method: 'POST',
		data,
	} );

/**
 * Values injected by the PHP page (doc/support links, asset base URL).
 *
 * @return {Object} Admin bootstrap data, or an empty object outside WP admin.
 */
export const adminData = () => window.flutterwaveAdminData || {};

/**
 * Query string for list filters. Arrays are sent comma separated.
 *
 * @param {Object} params Parameters.
 * @return {string} Query string without the "?".
 */
export const toQuery = ( params ) =>
	new URLSearchParams(
		Object.entries( params )
			.filter( ( [ , value ] ) =>
				Array.isArray( value )
					? value.length
					: '' !== value && undefined !== value && null !== value
			)
			.map( ( [ key, value ] ) => [
				key,
				Array.isArray( value ) ? value.join( ',' ) : String( value ),
			] )
	).toString();

export const fetchPayments = ( params ) =>
	apiFetch( { path: `${ NAMESPACE }/payments?${ toQuery( params ) }` } );

export const verifyPayment = ( id ) =>
	apiFetch( {
		path: `${ NAMESPACE }/payments/${ id }/verify`,
		method: 'POST',
	} );

export const deletePayment = ( id ) =>
	apiFetch( { path: `${ NAMESPACE }/payments/${ id }`, method: 'DELETE' } );

export const fetchForms = () => apiFetch( { path: `${ NAMESPACE }/forms` } );

export const createFormPage = ( data ) =>
	apiFetch( { path: `${ NAMESPACE }/forms/page`, method: 'POST', data } );

/**
 * Server-rendered HTML for a block, used by the form builder preview.
 *
 * @param {string} type       Block type without the namespace.
 * @param {Object} attributes Block attributes.
 * @return {Promise<Object>} `{ rendered }`.
 */
export const renderBlock = ( type, attributes ) =>
	apiFetch( {
		path: `/wp/v2/block-renderer/flutterwave/${ type }?context=edit`,
		method: 'POST',
		data: { attributes },
	} );

export const fetchIntegrations = () =>
	apiFetch( { path: `${ NAMESPACE }/integrations` } );

export const setIntegration = ( id, enabled ) =>
	apiFetch( {
		path: `${ NAMESPACE }/integrations/${ id }`,
		method: 'POST',
		data: { enabled },
	} );

/**
 * Install a plugin from WordPress.org and activate it, through core's REST API.
 *
 * @param {string} slug WordPress.org slug.
 * @return {Promise} The plugin.
 */
export const installPlugin = ( slug ) =>
	apiFetch( {
		path: '/wp/v2/plugins',
		method: 'POST',
		data: { slug, status: 'active' },
	} );

/**
 * Activate an installed plugin.
 *
 * @param {string} plugin Plugin id as core's REST API names it, e.g. "woocommerce/woocommerce".
 * @return {Promise} The plugin.
 */
export const activatePlugin = ( plugin ) =>
	apiFetch( {
		path: `/wp/v2/plugins/${ plugin }`,
		method: 'POST',
		data: { status: 'active' },
	} );

/**
 * Update the current user's guided tips: `{ enabled }`, `{ seen }` or `{ reset: true }`.
 *
 * @param {Object} data Changes.
 * @return {Promise} The saved state.
 */
export const saveTour = ( data ) =>
	apiFetch( {
		path: `${ NAMESPACE }/tour`,
		method: 'POST',
		data,
		// Finish saving even when the merchant closes a tip and leaves the page at once.
		keepalive: true,
	} );
