/**
 * Building blocks for the Payment Forms and Transactions screens: popovers,
 * multi-selects, status pills, the details drawer, modal and pagination.
 */

import {
	useEffect,
	useId,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { CheckIcon, ChevronDown } from '../icons';
import { PRESETS, earliestDate, toIsoDate } from '../lib/dates';

/**
 * Close something when the user clicks outside it or presses Escape.
 *
 * @param {Object}   ref     Ref to the element.
 * @param {boolean}  active  Whether to listen.
 * @param {Function} onClose Close handler.
 */
export const useDismiss = ( ref, active, onClose ) => {
	useEffect( () => {
		if ( ! active ) {
			return undefined;
		}

		const onPointer = ( event ) => {
			if ( ref.current && ! ref.current.contains( event.target ) ) {
				onClose();
			}
		};
		const onKey = ( event ) => {
			if ( 'Escape' === event.key ) {
				onClose();
			}
		};

		document.addEventListener( 'mousedown', onPointer );
		document.addEventListener( 'keydown', onKey );

		return () => {
			document.removeEventListener( 'mousedown', onPointer );
			document.removeEventListener( 'keydown', onKey );
		};
	}, [ ref, active, onClose ] );
};

/**
 * A grey toolbar button with a trailing icon, as on the Flutterwave dashboard.
 *
 * @param {Object} props
 * @param {*}      props.icon     Trailing icon.
 * @param {*}      props.children Label.
 * @return {Element} The button.
 */
export const ToolbarButton = ( { icon, children, ...rest } ) => (
	<button type="button" className="flw-toolbar-btn" { ...rest }>
		<span>{ children }</span>
		{ icon }
	</button>
);

/**
 * A panel anchored under its trigger.
 *
 * @param {Object}   props
 * @param {boolean}  props.open     Whether it is shown.
 * @param {Function} props.onClose  Close handler.
 * @param {*}        props.trigger  The trigger element.
 * @param {string}   props.label    Accessible name.
 * @param {*}        props.children Panel body.
 * @return {Element} The popover.
 */
export const Popover = ( { open, onClose, trigger, label, children } ) => {
	const ref = useRef( null );
	useDismiss( ref, open, onClose );

	return (
		<div className="flw-popover-anchor" ref={ ref }>
			{ trigger }
			{ open && (
				<div className="flw-popover" role="dialog" aria-label={ label }>
					{ children }
				</div>
			) }
		</div>
	);
};

const PRESET_LABELS = {
	today: __( 'Today', 'rave-payment-forms' ),
	'7d': __( 'Last 7 days', 'rave-payment-forms' ),
	'30d': __( '30 days', 'rave-payment-forms' ),
	'1y': __( '1 year', 'rave-payment-forms' ),
};

/**
 * Today / Last 7 days / 30 days / 1 year.
 *
 * @param {Object}   props
 * @param {string}   props.value    Active preset, or '' for a custom range.
 * @param {Function} props.onChange Receives the preset.
 * @return {Element} The preset row.
 */
export const DatePresets = ( { value, onChange } ) => (
	<div
		className="flw-presets"
		role="group"
		aria-label={ __( 'Date range', 'rave-payment-forms' ) }
	>
		{ PRESETS.map( ( preset ) => (
			<button
				key={ preset }
				type="button"
				className={ `flw-preset ${ value === preset ? 'is-active' : '' }` }
				aria-pressed={ value === preset }
				onClick={ () => onChange( preset ) }
			>
				{ PRESET_LABELS[ preset ] }
			</button>
		) ) }
	</div>
);

/**
 * Two date inputs limited to the last year.
 *
 * @param {Object}   props
 * @param {string}   props.from     Start date.
 * @param {string}   props.to       End date.
 * @param {Function} props.onChange Receives `{ from, to }`.
 * @return {Element} The fields.
 */
export const DateRange = ( { from, to, onChange } ) => {
	const min = earliestDate();
	const max = toIsoDate( new Date() );

	return (
		<div className="flw-daterange">
			<input
				type="date"
				className="flw-daterange__input"
				aria-label={ __( 'From', 'rave-payment-forms' ) }
				value={ from }
				min={ min }
				max={ to || max }
				onChange={ ( event ) =>
					onChange( { from: event.target.value, to } )
				}
			/>
			<input
				type="date"
				className="flw-daterange__input"
				aria-label={ __( 'To', 'rave-payment-forms' ) }
				value={ to }
				min={ from || min }
				max={ max }
				onChange={ ( event ) =>
					onChange( { from, to: event.target.value } )
				}
			/>
		</div>
	);
};

/**
 * Dropdown of checkboxes, optionally searchable.
 *
 * @param {Object}   props
 * @param {string}   props.label        Field label.
 * @param {Array}    props.options      `{ value, label }` pairs.
 * @param {string[]} props.value        Selected values.
 * @param {Function} props.onChange     Receives the new selection.
 * @param {string}   props.placeholder  Shown when nothing is selected.
 * @param {boolean}  [props.searchable] Type to filter options.
 * @return {Element} The multi-select.
 */
export const MultiSelect = ( {
	label,
	options,
	value,
	onChange,
	placeholder,
	searchable,
} ) => {
	const [ open, setOpen ] = useState( false );
	const [ query, setQuery ] = useState( '' );
	const ref = useRef( null );
	const id = useId();
	useDismiss( ref, open, () => setOpen( false ) );

	const visible = useMemo(
		() =>
			options.filter( ( option ) =>
				option.label
					.toLowerCase()
					.includes( query.trim().toLowerCase() )
			),
		[ options, query ]
	);

	const summary = value.length
		? options
				.filter( ( option ) => value.includes( option.value ) )
				.map( ( option ) => option.label )
				.join( ', ' )
		: '';

	const toggle = ( optionValue ) =>
		onChange(
			value.includes( optionValue )
				? value.filter( ( item ) => item !== optionValue )
				: [ ...value, optionValue ]
		);

	return (
		<div className="flw-filter-field" ref={ ref }>
			<span className="flw-filter-field__label" id={ `${ id }-label` }>
				{ label }
			</span>
			<div className={ `flw-multiselect ${ open ? 'is-open' : '' }` }>
				{ searchable ? (
					<input
						type="text"
						role="combobox"
						className="flw-multiselect__control"
						aria-labelledby={ `${ id }-label` }
						aria-expanded={ open }
						aria-autocomplete="list"
						placeholder={ summary || placeholder }
						value={ query }
						onFocus={ () => setOpen( true ) }
						onChange={ ( event ) => {
							setQuery( event.target.value );
							setOpen( true );
						} }
					/>
				) : (
					<button
						type="button"
						className="flw-multiselect__control"
						aria-labelledby={ `${ id }-label` }
						aria-expanded={ open }
						onClick={ () => setOpen( ! open ) }
					>
						<span className={ summary ? '' : 'is-placeholder' }>
							{ summary || placeholder }
						</span>
					</button>
				) }
				<span className="flw-multiselect__chevron" aria-hidden="true">
					<ChevronDown />
				</span>
				{ open && (
					<ul
						className="flw-multiselect__list"
						role="listbox"
						aria-multiselectable="true"
					>
						{ visible.length ? (
							visible.map( ( option ) => {
								const checked = value.includes( option.value );
								return (
									<li key={ option.value }>
										<label
											className="flw-multiselect__option"
											htmlFor={ `${ id }-${ option.value }` }
										>
											<input
												id={ `${ id }-${ option.value }` }
												type="checkbox"
												checked={ checked }
												onChange={ () =>
													toggle( option.value )
												}
											/>
											<span
												className="flw-multiselect__box"
												aria-hidden="true"
											>
												<CheckIcon />
											</span>
											{ option.label }
										</label>
									</li>
								);
							} )
						) : (
							<li className="flw-multiselect__empty">
								{ __( 'No matches', 'rave-payment-forms' ) }
							</li>
						) }
					</ul>
				) }
			</div>
		</div>
	);
};

const STATUS_LABELS = {
	successful: __( 'Successful', 'rave-payment-forms' ),
	pending: __( 'Pending', 'rave-payment-forms' ),
	failed: __( 'Failed', 'rave-payment-forms' ),
	cancelled: __( 'Cancelled', 'rave-payment-forms' ),
	review: __( 'Needs review', 'rave-payment-forms' ),
	publish: __( 'Published', 'rave-payment-forms' ),
	draft: __( 'Draft', 'rave-payment-forms' ),
	future: __( 'Scheduled', 'rave-payment-forms' ),
	private: __( 'Private', 'rave-payment-forms' ),
	synced: __( 'Synced pattern', 'rave-payment-forms' ),
};

export const statusLabel = ( status ) => STATUS_LABELS[ status ] || status;

/**
 * Coloured status label.
 *
 * @param {Object} props
 * @param {string} props.status  Status key.
 * @param {string} [props.title] Full stored status, shown on hover.
 * @return {Element} The pill.
 */
export const StatusPill = ( { status, title } ) => (
	<span className={ `flw-pill flw-pill--${ status }` } title={ title }>
		{ statusLabel( status ) }
	</span>
);

/**
 * Previous / Next pagination.
 *
 * @param {Object}   props
 * @param {number}   props.page     Current page.
 * @param {number}   props.pages    Total pages.
 * @param {Function} props.onChange Receives the new page.
 * @return {Element|null} The pager.
 */
export const Pagination = ( { page, pages, onChange } ) => {
	if ( pages <= 1 ) {
		return null;
	}

	return (
		<nav
			className="flw-pagination"
			aria-label={ __( 'Pages', 'rave-payment-forms' ) }
		>
			<button
				type="button"
				className="flw-page-btn"
				disabled={ page <= 1 }
				onClick={ () => onChange( page - 1 ) }
			>
				{ __( 'Previous', 'rave-payment-forms' ) }
			</button>
			<button
				type="button"
				className="flw-page-btn"
				disabled={ page >= pages }
				onClick={ () => onChange( page + 1 ) }
			>
				{ __( 'Next', 'rave-payment-forms' ) }
			</button>
			<span className="flw-pagination__status">
				{ sprintf(
					/* translators: 1: current page, 2: total pages. */
					__( 'Page %1$d of %2$d', 'rave-payment-forms' ),
					page,
					pages
				) }
			</span>
		</nav>
	);
};

/**
 * Right-hand slide-over panel.
 *
 * @param {Object}   props
 * @param {string}   props.title    Heading.
 * @param {Function} props.onClose  Close handler.
 * @param {*}        props.children Body.
 * @param {*}        [props.footer] Footer actions.
 * @return {Element} The drawer.
 */
export const Drawer = ( { title, onClose, children, footer } ) => {
	const ref = useRef( null );
	useDismiss( ref, true, onClose );

	return (
		<div className="flw-overlay">
			<aside
				className="flw-drawer"
				ref={ ref }
				role="dialog"
				aria-modal="true"
				aria-label={ title }
			>
				<header className="flw-drawer__header">
					<h2>{ title }</h2>
					<button
						type="button"
						className="flw-icon-btn"
						aria-label={ __( 'Close', 'rave-payment-forms' ) }
						onClick={ onClose }
					>
						&times;
					</button>
				</header>
				<div className="flw-drawer__body">{ children }</div>
				{ footer && (
					<footer className="flw-drawer__footer">{ footer }</footer>
				) }
			</aside>
		</div>
	);
};

/**
 * Centred dialog.
 *
 * @param {Object}   props
 * @param {string}   props.title    Heading.
 * @param {Function} props.onClose  Close handler.
 * @param {*}        props.children Body.
 * @param {*}        [props.footer] Footer actions.
 * @return {Element} The modal.
 */
export const Modal = ( { title, onClose, children, footer } ) => {
	const ref = useRef( null );
	useDismiss( ref, true, onClose );

	return (
		<div className="flw-overlay flw-overlay--center">
			<div
				className="flw-modal"
				ref={ ref }
				role="dialog"
				aria-modal="true"
				aria-label={ title }
			>
				<header className="flw-modal__header">
					<h2>{ title }</h2>
					<button
						type="button"
						className="flw-icon-btn"
						aria-label={ __( 'Close', 'rave-payment-forms' ) }
						onClick={ onClose }
					>
						&times;
					</button>
				</header>
				<div className="flw-modal__body">{ children }</div>
				{ footer && (
					<footer className="flw-modal__footer">{ footer }</footer>
				) }
			</div>
		</div>
	);
};

/**
 * Definition list row for the details drawer.
 *
 * @param {Object} props
 * @param {string} props.label    Term.
 * @param {*}      props.children Value.
 * @return {Element} The row.
 */
export const DetailRow = ( { label, children } ) => (
	<div className="flw-detail">
		<dt>{ label }</dt>
		<dd>{ children || '—' }</dd>
	</div>
);
