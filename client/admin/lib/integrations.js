/**
 * What an integration card offers next, worked out from the state the
 * /integrations route reports.
 */

import { __, sprintf } from '@wordpress/i18n';

export const CATEGORIES = {
	ecommerce: __( 'E-commerce', 'rave-payment-forms' ),
	digital: __( 'Digital products', 'rave-payment-forms' ),
	donations: __( 'Donations', 'rave-payment-forms' ),
	forms: __( 'Forms', 'rave-payment-forms' ),
	memberships: __( 'Memberships', 'rave-payment-forms' ),
	courses: __( 'Courses', 'rave-payment-forms' ),
	events: __( 'Events', 'rave-payment-forms' ),
};

/**
 * The step a plugin needs: installing, activating, or nothing.
 *
 * @param {Object}  plugin           `{ name, slug, rest, state }`.
 * @param {Object}  caps             Capabilities.
 * @param {boolean} caps.canInstall  Whether plugins can be installed.
 * @param {boolean} caps.canActivate Whether plugins can be activated.
 * @return {Object|null} The step, or null when the plugin is active.
 */
const pluginStep = ( plugin, { canInstall, canActivate } ) => {
	if ( 'active' === plugin.state ) {
		return null;
	}

	if ( 'inactive' === plugin.state ) {
		return canActivate
			? {
					action: 'activate',
					target: plugin.rest,
					label: sprintf(
						/* translators: %s: plugin name. */
						__( 'Activate %s', 'rave-payment-forms' ),
						plugin.name
					),
				}
			: {
					action: 'blocked',
					label: sprintf(
						/* translators: %s: plugin name. */
						__(
							'Ask an administrator to activate %s.',
							'rave-payment-forms'
						),
						plugin.name
					),
				};
	}

	return canInstall
		? {
				action: 'install',
				target: plugin.slug,
				label: sprintf(
					/* translators: %s: plugin name. */
					__( 'Install %s', 'rave-payment-forms' ),
					plugin.name
				),
			}
		: {
				action: 'blocked',
				label: sprintf(
					/* translators: %s: plugin name. */
					__( '%s is not installed.', 'rave-payment-forms' ),
					plugin.name
				),
			};
};

/**
 * The next thing a merchant can do with an integration.
 *
 * @param {Object} item Integration from the /integrations route.
 * @param {Object} caps `{ canInstall, canActivate }`.
 * @return {Object} `{ action, label, target? }`, where action is one of
 *                  install, activate, enable, manage, blocked or soon.
 */
export const nextStep = ( item, caps ) => {
	if ( 'coming-soon' === item.kind ) {
		return {
			action: 'soon',
			label: __( 'Coming soon', 'rave-payment-forms' ),
		};
	}

	const host = pluginStep( item.host, caps );

	if ( host ) {
		return host;
	}

	if ( 'extension' === item.kind ) {
		const extension = pluginStep( item.extension, caps );

		if ( extension ) {
			return extension;
		}
	}

	if ( 'built-in' === item.kind && ! item.enabled ) {
		return {
			action: 'enable',
			label: __( 'Enable', 'rave-payment-forms' ),
		};
	}

	return { action: 'manage', label: __( 'Manage', 'rave-payment-forms' ) };
};

/**
 * The status pill for a card.
 *
 * @param {Object} item Integration.
 * @return {Object} `{ tone, label }`.
 */
export const cardStatus = ( item ) => {
	if ( 'coming-soon' === item.kind ) {
		return {
			tone: 'soon',
			label: __( 'Coming soon', 'rave-payment-forms' ),
		};
	}

	if ( item.enabled ) {
		return { tone: 'active', label: __( 'Active', 'rave-payment-forms' ) };
	}

	if ( 'missing' === item.host.state ) {
		return {
			tone: 'idle',
			label: __( 'Not installed', 'rave-payment-forms' ),
		};
	}

	return {
		tone: 'ready',
		label: __( 'Ready to connect', 'rave-payment-forms' ),
	};
};

/**
 * Whether the host plugin's currency cannot be charged through Flutterwave.
 *
 * @param {Object}   item       Integration.
 * @param {string[]} currencies Currencies Flutterwave supports here.
 * @return {boolean} True when the currency is known and unsupported.
 */
export const unsupportedCurrency = ( item, currencies ) =>
	Boolean( item.currency ) && ! currencies.includes( item.currency );
