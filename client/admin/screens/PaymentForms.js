/**
 * Payment Forms: every Flutterwave block and shortcode in use, with the pages
 * they are on and the payments each page has taken, plus a builder for new forms.
 */

import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Page, Button, Toast } from '../components';
import { HelpMenu, Tour, useTour } from '../components/tour';
import { StatusPill } from '../components/list';
import { ExternalIcon, PlusIcon, DocsIllustration } from '../icons';
import { adminData, fetchForms } from '../lib/api';
import { formatMoney } from '../lib/money';
import { FORM_TYPES, typeLabel } from './form-types';
import FormBuilder from './FormBuilder';

const TABS = [
	{ key: 'all', label: __( 'All forms', 'rave-payment-forms' ) },
	...FORM_TYPES.map( ( type ) => ( { key: type.key, label: type.plural } ) ),
];

/**
 * The page status shown in the Status column.
 *
 * @param {Object} page Page data.
 * @return {string} Status key.
 */
const pageStatus = ( page ) =>
	'wp_block' === page.type ? 'synced' : page.status;

/**
 * "Customer enters", "NGN 5,000" or "—".
 *
 * @param {Object} item Form.
 * @return {*} Amount cell content.
 */
const amountCell = ( item ) => {
	if ( 'payment-methods' === item.type ) {
		return <span className="flw-cell-muted">—</span>;
	}

	if ( ! item.amount ) {
		return (
			<span className="flw-cell-muted">
				{ __( 'Customer enters', 'rave-payment-forms' ) }
			</span>
		);
	}

	return (
		<strong>
			{ formatMoney(
				item.amount,
				item.currency || adminData().siteCurrency,
				{ trimZeros: true }
			) }
		</strong>
	);
};

/**
 * "3 · NGN 15,000" or "—".
 *
 * @param {Object} payments Payment stats.
 * @return {*} Payments cell content.
 */
const paymentsCell = ( payments ) => {
	if ( ! payments.count ) {
		return <span className="flw-cell-muted">—</span>;
	}

	return (
		<>
			<span className="flw-cell-primary">
				{ sprintf(
					/* translators: %d: number of successful payments. */
					_n(
						'%d payment',
						'%d payments',
						payments.count,
						'rave-payment-forms'
					),
					payments.count
				) }
			</span>
			<span className="flw-cell-secondary">
				{ payments.totals
					.map( ( total ) =>
						formatMoney( total.amount, total.currency, {
							trimZeros: true,
						} )
					)
					.join( ' · ' ) }
			</span>
		</>
	);
};

const PaymentForms = () => {
	const [ data, setData ] = useState( null );
	const tour = useTour( 'forms', { ready: null !== data } );
	const [ tab, setTab ] = useState( 'all' );
	const [ building, setBuilding ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const { canEditPages, transactions } = adminData();

	useEffect( () => {
		fetchForms()
			.then( setData )
			.catch( ( error ) =>
				setNotice( {
					status: 'error',
					message:
						error?.message ||
						__(
							'Could not load your payment forms.',
							'rave-payment-forms'
						),
				} )
			);
	}, [] );

	const items = useMemo( () => data?.items ?? [], [ data ] );
	const visible =
		'all' === tab ? items : items.filter( ( item ) => item.type === tab );
	const counts = useMemo(
		() =>
			items.reduce(
				( acc, item ) => ( {
					...acc,
					[ item.type ]: ( acc[ item.type ] || 0 ) + 1,
				} ),
				{ all: items.length }
			),
		[ items ]
	);

	const newForm = canEditPages && (
		<Button data-tour="new-form" onClick={ () => setBuilding( true ) }>
			{ __( 'New form', 'rave-payment-forms' ) }
			<PlusIcon />
		</Button>
	);

	return (
		<Page
			wide
			backLabel={ __( 'Payment Forms', 'rave-payment-forms' ) }
			actions={ <HelpMenu tour={ tour } /> }
		>
			<Tour tour={ tour } />
			{ notice && (
				<Toast { ...notice } onDismiss={ () => setNotice( null ) } />
			) }

			<div className="flw-tabs" role="tablist" data-tour="form-types">
				{ TABS.map( ( item ) => (
					<button
						key={ item.key }
						type="button"
						role="tab"
						aria-selected={ tab === item.key }
						className={ `flw-tab ${ tab === item.key ? 'is-active' : '' }` }
						onClick={ () => setTab( item.key ) }
					>
						{ item.label }
						{ counts[ item.key ] ? (
							<span className="flw-tab__count">
								{ counts[ item.key ] }
							</span>
						) : null }
					</button>
				) ) }
			</div>

			<div className="flw-list-header">
				<h1 className="flw-list-title">
					{ sprintf(
						/* translators: %d: number of payment forms. */
						_n(
							'%d Payment form',
							'%d Payment forms',
							visible.length,
							'rave-payment-forms'
						),
						visible.length
					) }
				</h1>
				<div className="flw-list-actions">
					{ transactions && (
						<a className="flw-toolbar-btn" href={ transactions }>
							<span>
								{ __(
									'View transactions',
									'rave-payment-forms'
								) }
							</span>
						</a>
					) }
					{ newForm }
				</div>
			</div>

			{ null === data && ! notice && (
				<div className="flw-table-wrap is-loading">
					<div className="flw-skeleton" />
				</div>
			) }

			{ data && 0 === items.length && (
				<div className="flw-empty flw-empty--hero">
					<div className="flw-empty__art">
						<DocsIllustration />
					</div>
					<h2>
						{ __(
							'Create your first payment form',
							'rave-payment-forms'
						) }
					</h2>
					<p>
						{ __(
							'Payment buttons, forms, donation forms and pricing cards you add to your pages will be listed here with the payments they collect.',
							'rave-payment-forms'
						) }
					</p>
					{ newForm }
				</div>
			) }

			{ data && items.length > 0 && (
				<div className="flw-table-wrap">
					<table className="flw-table">
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Form', 'rave-payment-forms' ) }
								</th>
								<th scope="col">
									{ __( 'Amount', 'rave-payment-forms' ) }
								</th>
								<th scope="col">
									{ __( 'Used on', 'rave-payment-forms' ) }
								</th>
								<th scope="col">
									{ __( 'Status', 'rave-payment-forms' ) }
								</th>
								<th scope="col">
									{ __( 'Payments', 'rave-payment-forms' ) }
								</th>
								<th scope="col">
									<span className="screen-reader-text">
										{ __(
											'Actions',
											'rave-payment-forms'
										) }
									</span>
								</th>
							</tr>
						</thead>
						<tbody>
							{ visible.map( ( item, row ) => {
								const type = FORM_TYPES.find(
									( candidate ) => candidate.key === item.type
								);
								const Icon = type?.icon;

								return (
									<tr
										key={ item.id }
										data-tour={
											0 === row ? 'form-row' : undefined
										}
									>
										<td>
											<div className="flw-form-cell">
												<span
													className="flw-form-cell__icon"
													aria-hidden="true"
												>
													{ Icon }
												</span>
												<span>
													<span className="flw-cell-primary">
														{ item.label ||
															typeLabel(
																item.type
															) }
													</span>
													<span className="flw-cell-secondary">
														{ typeLabel(
															item.type
														) }{ ' ' }
														·{ ' ' }
														{ 'block' === item.kind
															? __(
																	'Block',
																	'rave-payment-forms'
																)
															: __(
																	'Shortcode',
																	'rave-payment-forms'
																) }
													</span>
												</span>
											</div>
										</td>
										<td>{ amountCell( item ) }</td>
										<td>
											<span className="flw-cell-primary">
												{ item.page.title }
											</span>
											<span className="flw-cell-secondary">
												{ sprintf(
													/* translators: %s: date the page was last changed. */
													__(
														'Updated %s',
														'rave-payment-forms'
													),
													item.page.modified
												) }
											</span>
										</td>
										<td>
											<StatusPill
												status={ pageStatus(
													item.page
												) }
											/>
										</td>
										<td>
											{ paymentsCell( item.payments ) }
										</td>
										<td className="flw-table__actions">
											{ item.page.editUrl && (
												<a
													className="flw-row-link"
													href={ item.page.editUrl }
												>
													{ __(
														'Edit',
														'rave-payment-forms'
													) }
												</a>
											) }
											{ item.page.viewUrl && (
												<a
													className="flw-row-link"
													href={ item.page.viewUrl }
													target="_blank"
													rel="noreferrer noopener"
												>
													{ __(
														'View',
														'rave-payment-forms'
													) }
													<ExternalIcon />
												</a>
											) }
										</td>
									</tr>
								);
							} ) }
						</tbody>
					</table>
					{ 0 === visible.length && (
						<div className="flw-empty">
							<p>
								{ __(
									'No forms of this type yet.',
									'rave-payment-forms'
								) }
							</p>
						</div>
					) }
				</div>
			) }

			<p className="flw-list-footnote">
				{ __(
					'Payments are counted per page for payments made since this version of the plugin. Shortcodes and blocks both appear here.',
					'rave-payment-forms'
				) }
			</p>

			{ building && (
				<FormBuilder
					onClose={ () => setBuilding( false ) }
					onNotice={ ( status, message ) =>
						setNotice( { status, message } )
					}
				/>
			) }
		</Page>
	);
};

export default PaymentForms;
