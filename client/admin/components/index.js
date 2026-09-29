/**
 * Shared presentational components for the Flutterwave admin app.
 */

import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ChevronLeft, ChevronDown, InfoIcon, CheckIcon } from '../icons';

/**
 * Page chrome: the sticky breadcrumb bar plus the two-column body.
 *
 * @param {Object}   props
 * @param {string}   props.backLabel Label shown next to the chevron.
 * @param {Function} [props.onBack]  Click handler; omit for a static crumb.
 * @param {*}        props.children  Page body.
 * @param {*}        [props.aside]   Right-hand rail (the stepper).
 * @param {boolean}  [props.wide]    Let the main column fill the screen, for
 *                                   surfaces that centre or span their own
 *                                   content.
 * @param {*}        [props.actions] Controls at the right of the breadcrumb bar.
 * @return {Element} The page shell.
 */
export const Page = ( {
	backLabel,
	onBack,
	children,
	aside,
	wide,
	actions,
} ) => (
	<div className="flw-page">
		<div className="flw-page__crumb">
			<div className="flw-page__crumb-inner">
				{ onBack ? (
					<button
						type="button"
						className="flw-crumb-btn"
						onClick={ onBack }
					>
						<ChevronLeft />
						<span>{ backLabel }</span>
					</button>
				) : (
					<span className="flw-crumb-btn flw-crumb-btn--static">
						<ChevronLeft />
						<span>{ backLabel }</span>
					</span>
				) }
				{ actions && (
					<div className="flw-page__actions">{ actions }</div>
				) }
			</div>
		</div>
		<div className={ `flw-page__body ${ aside ? 'has-aside' : '' }` }>
			<div className={ `flw-page__main ${ wide ? 'is-wide' : '' }` }>
				{ children }
			</div>
			{ aside && <div className="flw-page__aside">{ aside }</div> }
		</div>
	</div>
);

/**
 * "Step 1 of 3" pill.
 *
 * @param {Object} props
 * @param {number} props.current One-based step index.
 * @param {number} props.total   Total steps.
 * @return {Element} The pill.
 */
export const StepBadge = ( { current, total } ) => (
	<span className="flw-step-badge">
		{
			/* translators: 1: current step number, 2: total number of steps. */
			__( 'Step', 'rave-payment-forms' )
		}{ ' ' }
		<strong>
			{ current } { __( 'of', 'rave-payment-forms' ) } { total }
		</strong>
	</span>
);

/**
 * The progress rail on the right of each wizard step.
 *
 * @param {Object} props
 * @param {Array}  props.steps       Step descriptors.
 * @param {number} props.activeIndex Zero-based index of the current step.
 * @return {Element} The stepper card.
 */
export const Stepper = ( { steps, activeIndex } ) => (
	<div className="flw-stepper">
		{ steps.map( ( step, index ) => {
			const done = index < activeIndex;
			const active = index === activeIndex;

			return (
				<div
					key={ step.key }
					className={ `flw-stepper__item ${ done ? 'is-done' : '' } ${ active ? 'is-active' : '' }` }
				>
					<span className="flw-stepper__dot">
						{ ( done || active ) && <CheckIcon /> }
					</span>
					<span className="flw-stepper__label">{ step.label }</span>
				</div>
			);
		} ) }
	</div>
);

/**
 * Small "i" affordance that reveals help text on hover and focus.
 *
 * @param {Object} props
 * @param {string} props.text Tooltip copy.
 * @return {Element} The tooltip trigger.
 */
export const Tooltip = ( { text } ) => (
	<span
		className="flw-tooltip"
		tabIndex={ 0 }
		role="note"
		aria-label={ text }
	>
		<InfoIcon />
		<span className="flw-tooltip__bubble">{ text }</span>
	</span>
);

/**
 * Label + control wrapper with optional tooltip and error text.
 *
 * @param {Object} props
 * @param {string} props.id        Control id.
 * @param {string} props.label     Field label.
 * @param {string} [props.tooltip] Help text.
 * @param {string} [props.error]   Validation message.
 * @param {string} [props.hint]    Persistent helper text below the control.
 * @param {*}      props.children  The control itself.
 * @return {Element} The field.
 */
export const Field = ( { id, label, tooltip, error, hint, children } ) => (
	<div className={ `flw-field ${ error ? 'has-error' : '' }` }>
		<label className="flw-field__label" htmlFor={ id }>
			{ label }
			{ tooltip && <Tooltip text={ tooltip } /> }
		</label>
		{ children }
		{ hint && (
			<p className="flw-field__hint">
				<InfoIcon />
				<span>{ hint }</span>
			</p>
		) }
		{ error && <p className="flw-field__error">{ error }</p> }
	</div>
);

/**
 * Text input.
 *
 * @param {Object} props Standard input props.
 * @return {Element} The input.
 */
export const TextInput = ( props ) => (
	<input type="text" className="flw-input" { ...props } />
);

/**
 * Multi-line input.
 *
 * @param {Object} props Standard textarea props.
 * @return {Element} The textarea.
 */
export const TextArea = ( props ) => (
	<textarea className="flw-input flw-input--area" rows={ 5 } { ...props } />
);

/**
 * Select with a custom chevron.
 *
 * @param {Object} props
 * @param {Array}  props.options       `{ value, label }` pairs.
 * @param {string} [props.placeholder] Shown when the value is empty.
 * @return {Element} The select.
 */
export const Select = ( { options, placeholder, ...rest } ) => (
	<div className="flw-select">
		<select className="flw-input" { ...rest }>
			{ placeholder && <option value="">{ placeholder }</option> }
			{ options.map( ( option ) => (
				<option key={ option.value } value={ option.value }>
					{ option.label }
				</option>
			) ) }
		</select>
		<span className="flw-select__chevron">
			<ChevronDown />
		</span>
	</div>
);

/**
 * Square checkbox with an inline label.
 *
 * @param {Object}   props
 * @param {string}   props.id       Control id.
 * @param {boolean}  props.checked  Checked state.
 * @param {Function} props.onChange Receives the next boolean.
 * @param {string}   props.label    Inline label.
 * @return {Element} The checkbox row.
 */
export const Checkbox = ( { id, checked, onChange, label } ) => (
	<label className="flw-checkbox" htmlFor={ id }>
		<input
			id={ id }
			type="checkbox"
			checked={ !! checked }
			onChange={ ( event ) => onChange( event.target.checked ) }
		/>
		<span className="flw-checkbox__box" aria-hidden="true">
			<CheckIcon />
		</span>
		<span className="flw-checkbox__label">{ label }</span>
	</label>
);

/**
 * Primary/secondary button. Disabled primaries keep the faded orange from the
 * designs rather than going grey, so the affordance stays readable.
 *
 * @param {Object}  props
 * @param {string}  [props.variant] `primary` or `secondary`.
 * @param {boolean} [props.busy]    Renders the spinner and blocks clicks.
 * @param {*}       props.children  Button label.
 * @return {Element} The button.
 */
export const Button = ( { variant = 'primary', busy, children, ...rest } ) => (
	<button
		type="button"
		className={ `flw-btn flw-btn--${ variant } ${ busy ? 'is-busy' : '' }` }
		{ ...rest }
		disabled={ rest.disabled || busy }
	>
		{ busy && <span className="flw-btn__spinner" aria-hidden="true" /> }
		{ children }
	</button>
);

/**
 * Read-only field with a Copy button, used for the webhook URL.
 *
 * @param {Object} props
 * @param {string} props.value URL to copy.
 * @param {string} props.id    Control id.
 * @return {Element} The copy field.
 */
export const CopyField = ( { value, id } ) => {
	const [ copied, setCopied ] = useState( false );
	const timer = useRef( null );

	useEffect( () => () => clearTimeout( timer.current ), [] );

	const copy = async () => {
		try {
			if ( navigator.clipboard?.writeText ) {
				await navigator.clipboard.writeText( value );
			} else {
				const field = document.getElementById( id );
				field.select();
				document.execCommand( 'copy' );
			}
			setCopied( true );
			clearTimeout( timer.current );
			timer.current = setTimeout( () => setCopied( false ), 2000 );
		} catch {
			// Clipboard permission denied — the value stays selectable by hand.
		}
	};

	return (
		<div className="flw-copyfield">
			<input
				id={ id }
				className="flw-input"
				type="text"
				value={ value }
				readOnly
			/>
			<button
				type="button"
				className="flw-copyfield__btn"
				onClick={ copy }
			>
				{ copied
					? __( 'Copied', 'rave-payment-forms' )
					: __( 'Copy', 'rave-payment-forms' ) }
			</button>
		</div>
	);
};

/**
 * Masked input with a Show/Hide toggle, used for the secret key.
 *
 * @param {Object} props Standard input props.
 * @return {Element} The secret field.
 */
export const SecretInput = ( props ) => {
	const [ visible, setVisible ] = useState( false );

	return (
		<div className="flw-copyfield">
			<input
				className="flw-input"
				spellCheck="false"
				autoComplete="off"
				{ ...props }
				type={ visible ? 'text' : 'password' }
			/>
			<button
				type="button"
				className="flw-copyfield__btn"
				aria-controls={ props.id }
				aria-pressed={ visible }
				onClick={ () => setVisible( ! visible ) }
			>
				{ visible
					? __( 'Hide', 'rave-payment-forms' )
					: __( 'Show', 'rave-payment-forms' ) }
			</button>
		</div>
	);
};

/**
 * Full-screen loading state shown between the last wizard step and the success
 * screen, and while the initial settings request is in flight.
 *
 * @return {Element} The spinner.
 */
export const LoadingScreen = () => (
	<div className="flw-loading">
		<span
			className="flw-loading__ring"
			role="status"
			aria-label={ __( 'Loading', 'rave-payment-forms' ) }
		/>
	</div>
);

/**
 * Dismissible toast used for save confirmations and errors.
 *
 * @param {Object}   props
 * @param {string}   props.status    `success` or `error`.
 * @param {string}   props.message   Toast copy.
 * @param {Function} props.onDismiss Dismiss handler.
 * @return {Element} The toast.
 */
export const Toast = ( { status, message, onDismiss } ) => (
	<div className={ `flw-toast flw-toast--${ status }` } role="status">
		{ 'success' === status && (
			<span className="flw-toast__icon">
				<CheckIcon />
			</span>
		) }
		<span>{ message }</span>
		<button
			type="button"
			className="flw-toast__close"
			onClick={ onDismiss }
			aria-label={ __( 'Dismiss', 'rave-payment-forms' ) }
		>
			&times;
		</button>
	</div>
);

/**
 * On/off switch.
 *
 * @param {Object}   props
 * @param {boolean}  props.checked  Whether it is on.
 * @param {boolean}  props.disabled Whether it is busy.
 * @param {string}   props.label    Accessible name.
 * @param {Function} props.onChange Receives the new value.
 * @return {Element} The switch.
 */
export const Switch = ( { checked, disabled, label, onChange } ) => (
	<button
		type="button"
		role="switch"
		aria-checked={ checked }
		aria-label={ label }
		disabled={ disabled }
		className={ `flw-switch ${ checked ? 'is-on' : '' }` }
		onClick={ () => onChange( ! checked ) }
	>
		<span className="flw-switch__thumb" />
	</button>
);
