/**
 * Transactions: payments made through the forms, with date, status and
 * currency filters, a CSV download and a details drawer.
 */

import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Page, Button, Toast } from '../components';
import { HelpMenu, Tour, useTour } from '../components/tour';
import {
	DatePresets,
	DateRange,
	DetailRow,
	Drawer,
	MultiSelect,
	Pagination,
	Popover,
	StatusPill,
	ToolbarButton,
} from '../components/list';
import { DownloadIcon, FilterIcon, InfoIcon } from '../icons';
import {
	adminData,
	deletePayment,
	fetchPayments,
	toQuery,
	verifyPayment,
} from '../lib/api';
import { clampRange, presetRange } from '../lib/dates';
import { formatMoney } from '../lib/money';

const PER_PAGE = 20;

const STATUS_OPTIONS = [
	{ value: 'successful', label: __( 'Successful', 'rave-payment-forms' ) },
	{ value: 'pending', label: __( 'Pending', 'rave-payment-forms' ) },
	{ value: 'failed', label: __( 'Failed', 'rave-payment-forms' ) },
	{ value: 'cancelled', label: __( 'Cancelled', 'rave-payment-forms' ) },
	{ value: 'review', label: __( 'Needs review', 'rave-payment-forms' ) },
];

const defaultFilters = () => ( {
	preset: '1y',
	...presetRange( '1y' ),
	status: [],
	currency: [],
} );

/**
 * How many filter groups are in effect (dates always count).
 *
 * @param {Object} filters Filters.
 * @return {number} Count.
 */
const appliedCount = ( filters ) =>
	( filters.from || filters.to ? 1 : 0 ) +
	( filters.status.length ? 1 : 0 ) +
	( filters.currency.length ? 1 : 0 );

/**
 * The filter popover. Edits a draft and applies it on "Apply filter".
 *
 * @param {Object}   props
 * @param {Object}   props.filters    Applied filters.
 * @param {string[]} props.currencies Currencies to offer.
 * @param {Function} props.onApply    Receives the new filters.
 * @return {Element} The filter control.
 */
const FilterMenu = ( { filters, currencies, onApply } ) => {
	const [ open, setOpen ] = useState( false );
	const [ draft, setDraft ] = useState( filters );

	useEffect( () => {
		if ( open ) {
			setDraft( filters );
		}
	}, [ open, filters ] );

	const close = useCallback( () => setOpen( false ), [] );

	return (
		<Popover
			open={ open }
			onClose={ close }
			label={ __( 'Filter transactions', 'rave-payment-forms' ) }
			trigger={
				<ToolbarButton
					data-tour="filter"
					icon={ <FilterIcon /> }
					aria-expanded={ open }
					onClick={ () => setOpen( ! open ) }
				>
					{ sprintf(
						/* translators: %d: number of filters in effect. */
						__( 'Filter applied: %d', 'rave-payment-forms' ),
						appliedCount( filters )
					) }
				</ToolbarButton>
			}
		>
			<DatePresets
				value={ draft.preset }
				onChange={ ( preset ) =>
					setDraft( { ...draft, preset, ...presetRange( preset ) } )
				}
			/>
			<section className="flw-popover__section">
				<h3>{ __( 'Custom date range', 'rave-payment-forms' ) }</h3>
				<DateRange
					from={ draft.from }
					to={ draft.to }
					onChange={ ( range ) =>
						setDraft( { ...draft, preset: '', ...range } )
					}
				/>
				<p className="flw-popover__note">
					<InfoIcon />
					{ __(
						'Date range cannot exceed the last 1 year',
						'rave-payment-forms'
					) }
				</p>
			</section>
			<section className="flw-popover__section">
				<MultiSelect
					label={ __( 'Status', 'rave-payment-forms' ) }
					placeholder={ __( 'Select items', 'rave-payment-forms' ) }
					options={ STATUS_OPTIONS }
					value={ draft.status }
					onChange={ ( status ) => setDraft( { ...draft, status } ) }
				/>
			</section>
			<section className="flw-popover__section">
				<MultiSelect
					searchable
					label={ __( 'Currency', 'rave-payment-forms' ) }
					placeholder={ __(
						'Enter or select items',
						'rave-payment-forms'
					) }
					options={ currencies.map( ( code ) => ( {
						value: code,
						label: code,
					} ) ) }
					value={ draft.currency }
					onChange={ ( currency ) =>
						setDraft( { ...draft, currency } )
					}
				/>
			</section>
			<footer className="flw-popover__footer">
				<Button
					variant="outline"
					onClick={ () => setDraft( defaultFilters() ) }
				>
					{ __( 'Clear', 'rave-payment-forms' ) }
				</Button>
				<Button
					onClick={ () => {
						onApply( {
							...draft,
							...clampRange( draft.from, draft.to ),
						} );
						close();
					} }
				>
					{ __( 'Apply filter', 'rave-payment-forms' ) }
				</Button>
			</footer>
		</Popover>
	);
};

/**
 * The Custom Download popover: pick a range and download a CSV.
 *
 * @return {Element} The download control.
 */
const DownloadMenu = () => {
	const [ open, setOpen ] = useState( false );
	const [ range, setRange ] = useState( {
		preset: '30d',
		...presetRange( '30d' ),
	} );
	const close = useCallback( () => setOpen( false ), [] );

	const download = () => {
		const { from, to } = clampRange( range.from, range.to );
		window.location.assign(
			`${ adminData().exportUrl }&${ toQuery( { from, to } ) }`
		);
		close();
	};

	return (
		<Popover
			open={ open }
			onClose={ close }
			label={ __( 'Download transactions', 'rave-payment-forms' ) }
			trigger={
				<ToolbarButton
					data-tour="download"
					icon={ <DownloadIcon /> }
					aria-expanded={ open }
					onClick={ () => setOpen( ! open ) }
				>
					{ __( 'Custom Download', 'rave-payment-forms' ) }
				</ToolbarButton>
			}
		>
			<DatePresets
				value={ range.preset }
				onChange={ ( preset ) =>
					setRange( { preset, ...presetRange( preset ) } )
				}
			/>
			<section className="flw-popover__section">
				<h3>{ __( 'Custom date range', 'rave-payment-forms' ) }</h3>
				<DateRange
					from={ range.from }
					to={ range.to }
					onChange={ ( next ) => setRange( { preset: '', ...next } ) }
				/>
				<p className="flw-popover__note">
					<InfoIcon />
					{ __(
						'Downloads a CSV file you can open in any spreadsheet.',
						'rave-payment-forms'
					) }
				</p>
			</section>
			<footer className="flw-popover__footer">
				<Button variant="outline" onClick={ close }>
					{ __( 'Cancel', 'rave-payment-forms' ) }
				</Button>
				<Button onClick={ download }>
					{ __( 'Download', 'rave-payment-forms' ) }
				</Button>
			</footer>
		</Popover>
	);
};

/**
 * Details for one transaction, with re-verify and delete.
 *
 * @param {Object}   props
 * @param {Object}   props.item      Transaction.
 * @param {Function} props.onClose   Close handler.
 * @param {Function} props.onChanged Receives the updated item, or null when deleted.
 * @param {Function} props.onNotice  `( status, message ) => void`.
 * @return {Element} The drawer.
 */
const TransactionDrawer = ( { item, onClose, onChanged, onNotice } ) => {
	const [ busy, setBusy ] = useState( '' );

	const run = async ( action ) => {
		setBusy( action );
		try {
			if ( 'verify' === action ) {
				const updated = await verifyPayment( item.id );
				onChanged( updated );
				onNotice(
					'success',
					__(
						'Status updated from Flutterwave.',
						'rave-payment-forms'
					)
				);
			} else {
				await deletePayment( item.id );
				onChanged( null );
				onNotice(
					'success',
					__( 'Transaction deleted.', 'rave-payment-forms' )
				);
			}
		} catch ( error ) {
			onNotice(
				'error',
				error?.message ||
					__(
						'Something went wrong. Please try again.',
						'rave-payment-forms'
					)
			);
		} finally {
			setBusy( '' );
		}
	};

	return (
		<Drawer
			title={ __( 'Transaction details', 'rave-payment-forms' ) }
			onClose={ onClose }
			footer={
				<>
					{ 'successful' !== item.statusGroup && (
						<Button
							busy={ 'verify' === busy }
							disabled={ !! busy }
							onClick={ () => run( 'verify' ) }
						>
							{ __(
								'Re-verify with Flutterwave',
								'rave-payment-forms'
							) }
						</Button>
					) }
					<button
						type="button"
						className="flw-link-danger"
						disabled={ !! busy }
						onClick={ () => {
							if (
								// A native confirm is the lightest guard for a one-off destructive action.
								// eslint-disable-next-line no-alert
								window.confirm(
									__(
										'Delete this transaction record? This cannot be undone.',
										'rave-payment-forms'
									)
								)
							) {
								run( 'delete' );
							}
						} }
					>
						{ __( 'Delete record', 'rave-payment-forms' ) }
					</button>
				</>
			}
		>
			<p className="flw-drawer__amount">
				{ formatMoney( item.amount, item.currency ) }
			</p>
			<StatusPill status={ item.statusGroup } title={ item.status } />
			<dl className="flw-details">
				<DetailRow label={ __( 'Reference', 'rave-payment-forms' ) }>
					<code>{ item.reference }</code>
				</DetailRow>
				<DetailRow label={ __( 'Customer', 'rave-payment-forms' ) }>
					{ item.customerName }
				</DetailRow>
				<DetailRow label={ __( 'Email', 'rave-payment-forms' ) }>
					{ item.customerEmail }
				</DetailRow>
				<DetailRow label={ __( 'Date', 'rave-payment-forms' ) }>
					{ `${ item.dateDisplay } ${ item.timeDisplay }` }
				</DetailRow>
				<DetailRow
					label={ __( 'Status detail', 'rave-payment-forms' ) }
				>
					{ item.status }
				</DetailRow>
				<DetailRow
					label={ __( 'Flutterwave ID', 'rave-payment-forms' ) }
				>
					{ item.flutterwaveId }
				</DetailRow>
				<DetailRow label={ __( 'Paid on', 'rave-payment-forms' ) }>
					{ item.source && (
						<a
							href={ item.source.url }
							target="_blank"
							rel="noreferrer noopener"
						>
							{ item.source.title }
						</a>
					) }
				</DetailRow>
			</dl>
		</Drawer>
	);
};

const Transactions = () => {
	const [ filters, setFilters ] = useState( defaultFilters );
	const [ page, setPage ] = useState( 1 );
	const [ data, setData ] = useState( null );
	const tour = useTour( 'transactions', { ready: null !== data } );
	const [ loading, setLoading ] = useState( true );
	const [ selected, setSelected ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ reload, setReload ] = useState( 0 );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );

		fetchPayments( {
			page,
			per_page: PER_PAGE,
			from: filters.from,
			to: filters.to,
			status: filters.status,
			currency: filters.currency,
		} )
			.then( ( payload ) => ! cancelled && setData( payload ) )
			.catch(
				( error ) =>
					! cancelled &&
					setNotice( {
						status: 'error',
						message:
							error?.message ||
							__(
								'Could not load transactions.',
								'rave-payment-forms'
							),
					} )
			)
			.finally( () => ! cancelled && setLoading( false ) );

		return () => {
			cancelled = true;
		};
	}, [ filters, page, reload ] );

	useEffect( () => {
		if ( ! notice ) {
			return undefined;
		}
		const timer = setTimeout( () => setNotice( null ), 4000 );
		return () => clearTimeout( timer );
	}, [ notice ] );

	const total = data?.total ?? 0;
	const items = data?.items ?? [];

	return (
		<Page
			wide
			backLabel={ __( 'Transactions', 'rave-payment-forms' ) }
			actions={ <HelpMenu tour={ tour } /> }
		>
			<Tour tour={ tour } />
			{ notice && (
				<Toast { ...notice } onDismiss={ () => setNotice( null ) } />
			) }

			<div className="flw-list-header">
				<h1 className="flw-list-title">
					{ sprintf(
						/* translators: %d: number of transactions. */
						_n(
							'%d Transaction',
							'%d Transactions',
							total,
							'rave-payment-forms'
						),
						total
					) }
				</h1>
				<div className="flw-list-actions">
					<FilterMenu
						filters={ filters }
						currencies={ data?.currencies ?? [] }
						onApply={ ( next ) => {
							setPage( 1 );
							setFilters( next );
						} }
					/>
					<DownloadMenu />
				</div>
			</div>

			<div
				className={ `flw-table-wrap ${ loading ? 'is-loading' : '' }` }
			>
				<table className="flw-table">
					<thead>
						<tr>
							<th scope="col">
								{ __( 'Customer', 'rave-payment-forms' ) }
							</th>
							<th scope="col">
								{ __( 'Amount', 'rave-payment-forms' ) }
							</th>
							<th scope="col">
								{ __( 'Date', 'rave-payment-forms' ) }
							</th>
							<th scope="col">
								{ __( 'Status', 'rave-payment-forms' ) }
							</th>
						</tr>
					</thead>
					<tbody>
						{ items.map( ( item, row ) => (
							<tr
								key={ item.id }
								data-tour={
									0 === row ? 'transaction-row' : undefined
								}
								className="is-clickable"
								tabIndex={ 0 }
								onClick={ () => setSelected( item ) }
								onKeyDown={ ( event ) =>
									'Enter' === event.key && setSelected( item )
								}
							>
								<td>
									<span className="flw-cell-primary">
										{ item.customerName ||
											item.customerEmail ||
											__(
												'Unknown customer',
												'rave-payment-forms'
											) }
									</span>
									{ item.customerName &&
										item.customerEmail && (
											<span className="flw-cell-secondary">
												{ item.customerEmail }
											</span>
										) }
								</td>
								<td>
									<strong>
										{ formatMoney(
											item.amount,
											item.currency
										) }
									</strong>
								</td>
								<td>
									{ item.dateDisplay }{ ' ' }
									<span className="flw-cell-muted">
										{ item.timeDisplay }
									</span>
								</td>
								<td>
									<StatusPill
										status={ item.statusGroup }
										title={ item.status }
									/>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>

				{ ! loading && 0 === items.length && (
					<div className="flw-empty">
						<h2>
							{ __(
								'No transactions found',
								'rave-payment-forms'
							) }
						</h2>
						<p>
							{ appliedCount( filters ) > 1
								? __(
										'No payments match these filters.',
										'rave-payment-forms'
									)
								: __(
										'Payments made through your forms in this period will show up here.',
										'rave-payment-forms'
									) }
						</p>
						{ appliedCount( filters ) > 1 && (
							<Button
								variant="outline"
								onClick={ () => setFilters( defaultFilters() ) }
							>
								{ __( 'Clear filters', 'rave-payment-forms' ) }
							</Button>
						) }
					</div>
				) }
			</div>

			<Pagination
				page={ page }
				pages={ data?.pages ?? 0 }
				onChange={ setPage }
			/>

			{ selected && (
				<TransactionDrawer
					item={ selected }
					onClose={ () => setSelected( null ) }
					onNotice={ ( status, message ) =>
						setNotice( { status, message } )
					}
					onChanged={ ( updated ) => {
						setSelected( updated );
						setReload( ( value ) => value + 1 );
					} }
				/>
			) }
		</Page>
	);
};

export default Transactions;
