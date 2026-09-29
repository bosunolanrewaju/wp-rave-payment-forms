/**
 * Integrations: connect Flutterwave to the plugins merchants already use to
 * sell, take donations and run memberships.
 */

import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Page, Button, Switch, Toast } from '../components';
import { HelpMenu, Tour, useTour } from '../components/tour';
import { ExternalIcon } from '../icons';
import {
	activatePlugin,
	adminData,
	fetchIntegrations,
	installPlugin,
	setIntegration,
} from '../lib/api';
import {
	CATEGORIES,
	cardStatus,
	nextStep,
	unsupportedCurrency,
} from '../lib/integrations';

/**
 * Tile colours for each integration's monogram.
 */
const TILES = {
	woocommerce: [ '#f3ecfb', '#6b3fa0' ],
	'easy-digital-downloads': [ '#e8f1fb', '#1d5c96' ],
	givewp: [ '#e9f7ee', '#1f7a45' ],
};

/**
 * Initials for a monogram tile, e.g. "ED" for Easy Digital Downloads.
 *
 * @param {string} name Plugin name.
 * @return {string} Up to two letters.
 */
const initials = ( name ) =>
	name
		.split( /[\s-]+/ )
		.filter( Boolean )
		.slice( 0, 2 )
		.map( ( word ) => word[ 0 ].toUpperCase() )
		.join( '' );

/**
 * @param {Object} props
 * @param {Object} props.item The integration.
 * @return {Element} The monogram tile.
 */
const Monogram = ( { item } ) => {
	const [ background, color ] = TILES[ item.id ] || [ '#f2f4f7', '#475467' ];

	return (
		<span
			className="flw-integration__logo"
			style={ { background, color } }
			aria-hidden="true"
		>
			{ initials( item.name ) }
		</span>
	);
};

/**
 * One integration card.
 *
 * @param {Object}   props
 * @param {Object}   props.item       Integration.
 * @param {Object}   props.caps       `{ canInstall, canActivate }`.
 * @param {string[]} props.currencies Supported currencies.
 * @param {boolean}  props.busy       Whether an action is running on it.
 * @param {Function} props.onAction   `( item, step ) => void`.
 * @param {Function} props.onToggle   `( item, enabled ) => void`.
 * @return {Element} The card.
 */
const IntegrationCard = ( {
	item,
	caps,
	currencies,
	busy,
	onAction,
	onToggle,
} ) => {
	const step = nextStep( item, caps );
	const status = cardStatus( item );
	const soon = 'soon' === step.action;
	const builtIn = 'built-in' === item.kind;
	const currencyWarning = builtIn && unsupportedCurrency( item, currencies );

	return (
		<article
			className={ `flw-integration ${ soon ? 'is-soon' : '' }` }
			aria-label={ item.name }
		>
			<header className="flw-integration__head">
				<Monogram item={ item } />
				<span
					className={ `flw-integration__status is-${ status.tone }` }
				>
					{ status.label }
				</span>
			</header>

			<h3 className="flw-integration__name">{ item.name }</h3>
			<span className="flw-integration__category">
				{ CATEGORIES[ item.category ] }
			</span>
			<p className="flw-integration__desc">{ item.description }</p>

			{ 'extension' === item.kind && ! soon && (
				<p className="flw-integration__hint">
					{ sprintf(
						/* translators: %s: extension name. */
						__(
							'Uses the free %s extension.',
							'rave-payment-forms'
						),
						item.extension.name
					) }
				</p>
			) }

			{ currencyWarning && (
				<p className="flw-integration__warning" role="note">
					{ sprintf(
						/* translators: 1: currency code, 2: plugin name. */
						__(
							'%1$s, the currency set in %2$s, is not supported by Flutterwave.',
							'rave-payment-forms'
						),
						item.currency,
						item.host.name
					) }
				</p>
			) }

			{ ! soon && (
				<footer className="flw-integration__foot">
					{ ( 'install' === step.action ||
						'activate' === step.action ) && (
						<Button
							variant="outline"
							busy={ busy }
							onClick={ () => onAction( item, step ) }
						>
							{ step.label }
						</Button>
					) }

					{ 'enable' === step.action && (
						<Button
							busy={ busy }
							onClick={ () => onToggle( item, true ) }
						>
							{ step.label }
						</Button>
					) }

					{ 'blocked' === step.action && (
						<span className="flw-integration__blocked">
							{ step.label }
						</span>
					) }

					{ 'manage' === step.action && (
						<>
							<a
								className="flw-integration__manage"
								href={ item.manageUrl }
							>
								{ builtIn
									? __(
											'Gateway settings',
											'rave-payment-forms'
										)
									: __(
											'Manage settings',
											'rave-payment-forms'
										) }
								<ExternalIcon />
							</a>
							{ builtIn && (
								<Switch
									checked={ item.enabled }
									disabled={ busy }
									label={ sprintf(
										/* translators: %s: plugin name. */
										__(
											'Flutterwave for %s',
											'rave-payment-forms'
										),
										item.name
									) }
									onChange={ ( enabled ) =>
										onToggle( item, enabled )
									}
								/>
							) }
						</>
					) }
				</footer>
			) }
		</article>
	);
};

const FILTERS = [
	{ key: 'all', label: __( 'All', 'rave-payment-forms' ) },
].concat(
	Object.entries( CATEGORIES ).map( ( [ key, label ] ) => ( { key, label } ) )
);

const Integrations = () => {
	const [ data, setData ] = useState( null );
	const tour = useTour( 'integrations', { ready: null !== data } );
	const [ filter, setFilter ] = useState( 'all' );
	const [ busy, setBusy ] = useState( '' );
	const [ notice, setNotice ] = useState( null );

	const load = useCallback(
		() =>
			fetchIntegrations()
				.then( setData )
				.catch( ( error ) =>
					setNotice( {
						status: 'error',
						message:
							error?.message ||
							__(
								'Could not load integrations.',
								'rave-payment-forms'
							),
					} )
				),
		[]
	);

	useEffect( () => {
		load();
	}, [ load ] );

	const run = async ( item, task, success ) => {
		setBusy( item.id );
		try {
			await task();
			await load();
			setNotice( { status: 'success', message: success } );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error?.message ||
					__(
						'Something went wrong. Please try again.',
						'rave-payment-forms'
					),
			} );
		} finally {
			setBusy( '' );
		}
	};

	const onAction = ( item, step ) =>
		run(
			item,
			() =>
				'install' === step.action
					? installPlugin( step.target )
					: activatePlugin( step.target ),
			'install' === step.action
				? __( 'Plugin installed and activated.', 'rave-payment-forms' )
				: __( 'Plugin activated.', 'rave-payment-forms' )
		);

	const onToggle = ( item, enabled ) =>
		run(
			item,
			() => setIntegration( item.id, enabled ),
			enabled
				? sprintf(
						/* translators: %s: plugin name. */
						__(
							'Flutterwave is now a payment option in %s.',
							'rave-payment-forms'
						),
						item.name
					)
				: sprintf(
						/* translators: %s: plugin name. */
						__(
							'Flutterwave is no longer offered in %s.',
							'rave-payment-forms'
						),
						item.name
					)
		);

	const items = useMemo( () => data?.items ?? [], [ data ] );
	const shown =
		'all' === filter
			? items
			: items.filter( ( item ) => item.category === filter );
	const available = shown.filter( ( item ) => 'coming-soon' !== item.kind );
	const soon = shown.filter( ( item ) => 'coming-soon' === item.kind );
	const connected = items.filter( ( item ) => item.enabled ).length;
	const caps = {
		canInstall: !! data?.canInstall,
		canActivate: !! data?.canActivate,
	};

	const card = ( item ) => (
		<IntegrationCard
			key={ item.id }
			item={ item }
			caps={ caps }
			currencies={ data.currencies }
			busy={ busy === item.id }
			onAction={ onAction }
			onToggle={ onToggle }
		/>
	);

	return (
		<Page
			wide
			backLabel={ __( 'Integrations', 'rave-payment-forms' ) }
			actions={ <HelpMenu tour={ tour } /> }
		>
			<Tour tour={ tour } />
			{ notice && (
				<Toast { ...notice } onDismiss={ () => setNotice( null ) } />
			) }

			<div className="flw-list-header flw-integrations__header">
				<div>
					<h1 className="flw-list-title">
						{ __( 'Integrations', 'rave-payment-forms' ) }
					</h1>
					<p className="flw-integrations__lead">
						{ __(
							'Take Flutterwave payments inside the plugins you already use to sell products, collect donations and run memberships.',
							'rave-payment-forms'
						) }
					</p>
				</div>
				{ data && (
					<span className="flw-integrations__count">
						{ sprintf(
							/* translators: %d: number of connected integrations. */
							__( '%d connected', 'rave-payment-forms' ),
							connected
						) }
					</span>
				) }
			</div>

			<div
				className="flw-chips"
				role="group"
				aria-label={ __( 'Category', 'rave-payment-forms' ) }
			>
				{ FILTERS.filter(
					( item ) =>
						'all' === item.key ||
						items.some( ( entry ) => entry.category === item.key )
				).map( ( item ) => (
					<button
						key={ item.key }
						type="button"
						className={ `flw-preset ${ filter === item.key ? 'is-active' : '' }` }
						aria-pressed={ filter === item.key }
						onClick={ () => setFilter( item.key ) }
					>
						{ item.label }
					</button>
				) ) }
			</div>

			{ null === data && ! notice && (
				<div className="flw-integrations__grid is-loading" />
			) }

			{ data && available.length > 0 && (
				<section
					aria-labelledby="flw-integrations-available"
					data-tour="integrations-available"
				>
					<h2
						className="flw-section-title"
						id="flw-integrations-available"
					>
						{ __( 'Available', 'rave-payment-forms' ) }
					</h2>
					<div className="flw-integrations__grid">
						{ available.map( card ) }
					</div>
				</section>
			) }

			{ data && soon.length > 0 && (
				<section
					aria-labelledby="flw-integrations-soon"
					data-tour="integrations-soon"
				>
					<h2
						className="flw-section-title"
						id="flw-integrations-soon"
					>
						{ __( 'Coming soon', 'rave-payment-forms' ) }
					</h2>
					<div className="flw-integrations__grid">
						{ soon.map( card ) }
					</div>
				</section>
			) }

			<p className="flw-list-footnote">
				{ __(
					'Using another plugin to sell or take donations?',
					'rave-payment-forms'
				) }{ ' ' }
				<a
					href={ adminData().supportForum }
					target="_blank"
					rel="noreferrer noopener"
				>
					{ __( 'Tell us which one', 'rave-payment-forms' ) }
				</a>
			</p>
		</Page>
	);
};

export default Integrations;
