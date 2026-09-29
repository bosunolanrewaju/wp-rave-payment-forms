/**
 * Guided tips: a short tour per screen, and the Help menu that replays it or
 * turns tips on and off.
 *
 * A screen calls useTour(), passes the result to <HelpMenu> in the breadcrumb
 * bar, and renders <Tour> once. Tours start by themselves the first time a
 * screen is opened after onboarding, unless the user has turned tips off.
 */

import {
	createPortal,
	useCallback,
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Switch } from '.';
import { Popover } from './list';
import { ExternalIcon, HelpIcon } from '../icons';
import { adminData, saveTour } from '../lib/api';
import {
	pickTourState,
	placeTooltip,
	shouldAutoStart,
	tourKey,
	tourSteps,
} from '../lib/tour';

/**
 * Space left around the highlighted element.
 */
const SPOT_PADDING = 6;

/**
 * This browser's copy of a user's tour state.
 *
 * @param {number} userId User id.
 * @return {string} Storage key.
 */
const storageKey = ( userId ) => `flw_tour_${ userId || 0 }`;

/**
 * Read the browser copy; storage can be missing or blocked.
 *
 * @param {number} userId User id.
 * @return {Object|null} `{ enabled, seen, at }`.
 */
const readLocal = ( userId ) => {
	try {
		return JSON.parse(
			window.localStorage.getItem( storageKey( userId ) )
		);
	} catch {
		return null;
	}
};

/**
 * Write the browser copy.
 *
 * @param {number} userId User id.
 * @param {Object} state  `{ enabled, seen }`.
 */
const writeLocal = ( userId, state ) => {
	try {
		window.localStorage.setItem(
			storageKey( userId ),
			JSON.stringify( { ...state, at: Date.now() } )
		);
	} catch {
		// Private windows and blocked storage fall back to the server copy.
	}
};

/**
 * Tour state for a screen.
 *
 * @param {string}  screen              Screen name, e.g. "transactions".
 * @param {Object}  [options]
 * @param {boolean} [options.ready]     Whether the screen has rendered what the steps point at.
 * @param {boolean} [options.onboarded] Whether onboarding is finished; read from the page by default.
 * @return {Object} `{ running, steps, enabled, start, finish, setEnabled }`.
 */
export const useTour = ( screen, { ready = true, onboarded } = {} ) => {
	const data = adminData();
	const [ state, setState ] = useState( () =>
		pickTourState( data.tour, readLocal( data.userId ) )
	);
	const [ running, setRunning ] = useState( false );
	const started = useRef( false );
	const isOnboarded = undefined === onboarded ? data.onboarded : onboarded;

	useEffect( () => {
		if (
			! ready ||
			started.current ||
			! shouldAutoStart( state, screen, isOnboarded )
		) {
			return undefined;
		}

		// A short pause lets the page settle before the first tip appears.
		const timer = setTimeout( () => {
			started.current = true;
			setRunning( true );
		}, 500 );

		return () => clearTimeout( timer );
	}, [ ready, state, screen, isOnboarded ] );

	// Keep the browser copy in step, so a page opened before the save lands agrees.
	const update = ( next ) => {
		setState( next );
		writeLocal( data.userId, next );
	};

	const finish = () => {
		const key = tourKey( screen );

		setRunning( false );

		if ( ! state.seen.includes( key ) ) {
			update( { ...state, seen: [ ...state.seen, key ] } );
		}

		saveTour( { seen: key } ).catch( () => {} );
	};

	const setEnabled = ( enabled ) => {
		started.current = true;

		// Turning tips back on shows every page's tour again, starting with this one.
		update( { enabled, seen: enabled ? [] : state.seen } );
		setRunning( enabled );
		saveTour( enabled ? { enabled, reset: true } : { enabled } ).catch(
			() => {}
		);
	};

	return {
		running,
		steps: tourSteps()[ screen ] || [],
		enabled: state.enabled,
		start: () => setRunning( true ),
		finish,
		setEnabled,
	};
};

/**
 * A rect as plain numbers, relative to the viewport.
 *
 * @param {Element} element Element.
 * @return {Object} `{ top, left, width, height }`.
 */
const rectOf = ( element ) => {
	const box = element.getBoundingClientRect();

	return {
		top: box.top,
		left: box.left,
		width: box.width,
		height: box.height,
	};
};

/**
 * Whether an element is on screen and visible.
 *
 * @param {Element|null} element Element.
 * @return {boolean} True when it has a size.
 */
const isShown = ( element ) => {
	if ( ! element ) {
		return false;
	}

	const box = element.getBoundingClientRect();

	return box.width > 0 && box.height > 0;
};

/**
 * The running tour: dims the page, highlights one element and explains it.
 *
 * @param {Object} props
 * @param {Object} props.tour Result of useTour().
 * @return {Element|null} The tour.
 */
export const Tour = ( { tour } ) => {
	if ( ! tour.running || ! tour.steps.length ) {
		return null;
	}

	return <TourSteps steps={ tour.steps } onFinish={ tour.finish } />;
};

/**
 * @param {Object}   props
 * @param {Array}    props.steps    Steps.
 * @param {Function} props.onFinish Called when the tour is finished or skipped.
 * @return {Element|null} The current step.
 */
const TourSteps = ( { steps, onFinish } ) => {
	const [ index, setIndex ] = useState( 0 );
	const [ rect, setRect ] = useState( null );
	const [ located, setLocated ] = useState( false );
	const [ position, setPosition ] = useState( null );
	const target = useRef( null );
	const card = useRef( null );
	const step = steps[ index ];

	// The steps whose element is on the page right now: they decide "2 of 3",
	// where Back and Next go, and when Next becomes Done.
	const available = steps
		.map( ( candidate, i ) => i )
		.filter(
			( i ) =>
				i === index ||
				! steps[ i ].target ||
				isShown( document.querySelector( steps[ i ].target ) )
		);
	const place = available.indexOf( index );
	const isLast = place === available.length - 1;

	const go = useCallback(
		( next ) => {
			if ( undefined === next || next >= steps.length ) {
				onFinish();
				return;
			}

			setLocated( false );
			setPosition( null );
			setIndex( next );
		},
		[ steps.length, onFinish ]
	);
	const next = () => go( isLast ? undefined : available[ place + 1 ] );
	const back = () => place > 0 && go( available[ place - 1 ] );

	// Find the element this step points at, giving the page a moment to render it.
	useEffect( () => {
		if ( ! step.target ) {
			target.current = null;
			setRect( null );
			setLocated( true );
			return undefined;
		}

		let tries = 0;
		let timer;

		const find = () => {
			const element = document.querySelector( step.target );

			if ( isShown( element ) ) {
				const box = element.getBoundingClientRect();

				if ( box.top < 0 || box.bottom > window.innerHeight ) {
					element.scrollIntoView( { block: 'center' } );
				}

				target.current = element;
				setRect( rectOf( element ) );
				setLocated( true );
				return;
			}

			tries += 1;

			if ( tries < 8 ) {
				timer = setTimeout( find, 150 );
				return;
			}

			// Not on this page (an empty table, a menu hidden on a phone): skip it.
			go( index + 1 );
		};

		find();

		return () => clearTimeout( timer );
		// eslint-disable-next-line react-hooks/exhaustive-deps -- runs once per step.
	}, [ index ] );

	// Follow the element when the page scrolls or resizes.
	useEffect( () => {
		if ( ! located || ! target.current ) {
			return undefined;
		}

		const update = () =>
			target.current && setRect( rectOf( target.current ) );

		window.addEventListener( 'resize', update );
		window.addEventListener( 'scroll', update, true );

		return () => {
			window.removeEventListener( 'resize', update );
			window.removeEventListener( 'scroll', update, true );
		};
	}, [ located ] );

	// Measure the card, then place it beside the element.
	useLayoutEffect( () => {
		if ( ! located || ! card.current ) {
			return;
		}

		setPosition(
			placeTooltip(
				rect,
				{
					width: card.current.offsetWidth,
					height: card.current.offsetHeight,
				},
				{ width: window.innerWidth, height: window.innerHeight },
				step.placement
			)
		);
	}, [ located, rect, step ] );

	// Move focus to the card so keyboard and screen reader users follow along.
	useEffect( () => {
		if ( position ) {
			card.current?.focus( { preventScroll: true } );
		}
	}, [ index, Boolean( position ) ] ); // eslint-disable-line react-hooks/exhaustive-deps

	useEffect( () => {
		const onKey = ( event ) => {
			if ( 'Escape' === event.key ) {
				onFinish();
			} else if ( 'ArrowRight' === event.key ) {
				next();
			} else if ( 'ArrowLeft' === event.key ) {
				back();
			}
		};

		document.addEventListener( 'keydown', onKey );

		return () => document.removeEventListener( 'keydown', onKey );
	} );

	if ( ! located ) {
		return null;
	}

	const root =
		document.getElementById( 'flutterwave-admin-root' ) || document.body;
	const titleId = `flw-tour-title-${ index }`;

	return createPortal(
		<div className="flw-tour">
			<div className="flw-tour__blocker" aria-hidden="true" />
			{ rect ? (
				<div
					className="flw-tour__spot"
					aria-hidden="true"
					style={ {
						top: rect.top - SPOT_PADDING,
						left: rect.left - SPOT_PADDING,
						width: rect.width + SPOT_PADDING * 2,
						height: rect.height + SPOT_PADDING * 2,
					} }
				/>
			) : (
				<div className="flw-tour__dim" aria-hidden="true" />
			) }

			<div
				ref={ card }
				className={ `flw-tour__card is-${ position?.side || 'hidden' }` }
				role="dialog"
				aria-labelledby={ titleId }
				tabIndex={ -1 }
				style={ {
					top: position?.top ?? 0,
					left: position?.left ?? 0,
					visibility: position ? 'visible' : 'hidden',
				} }
			>
				{ null !== position?.arrow && position && (
					<span
						className="flw-tour__arrow"
						aria-hidden="true"
						style={
							'right' === position.side
								? { top: position.arrow }
								: { left: position.arrow }
						}
					/>
				) }

				{ available.length > 1 && (
					<span className="flw-tour__count">
						{ sprintf(
							/* translators: 1: step number, 2: number of steps. */
							__( '%1$d of %2$d', 'rave-payment-forms' ),
							place + 1,
							available.length
						) }
					</span>
				) }
				<h2 className="flw-tour__title" id={ titleId }>
					{ step.title }
				</h2>
				<p className="flw-tour__body">{ step.body }</p>

				<div className="flw-tour__foot">
					{ isLast ? (
						<span />
					) : (
						<button
							type="button"
							className="flw-tour__skip"
							onClick={ onFinish }
						>
							{ __( 'Skip tour', 'rave-payment-forms' ) }
						</button>
					) }
					<div className="flw-tour__nav">
						{ place > 0 && (
							<Button variant="outline" onClick={ back }>
								{ __( 'Back', 'rave-payment-forms' ) }
							</Button>
						) }
						<Button onClick={ next }>
							{ isLast
								? __( 'Done', 'rave-payment-forms' )
								: __( 'Next', 'rave-payment-forms' ) }
						</Button>
					</div>
				</div>
			</div>
		</div>,
		root
	);
};

/**
 * The Help menu in the breadcrumb bar.
 *
 * @param {Object} props
 * @param {Object} props.tour Result of useTour().
 * @return {Element} The menu.
 */
export const HelpMenu = ( { tour } ) => {
	const [ open, setOpen ] = useState( false );
	const close = () => setOpen( false );

	return (
		<Popover
			open={ open }
			onClose={ close }
			label={ __( 'Help', 'rave-payment-forms' ) }
			trigger={
				<button
					type="button"
					className="flw-help-btn"
					data-tour="help"
					aria-label={ __( 'Help', 'rave-payment-forms' ) }
					aria-expanded={ open }
					onClick={ () => setOpen( ! open ) }
				>
					<HelpIcon />
					<span>{ __( 'Help', 'rave-payment-forms' ) }</span>
				</button>
			}
		>
			<div className="flw-help">
				{ tour.steps.length > 0 && (
					<button
						type="button"
						className="flw-help__item"
						onClick={ () => {
							close();
							tour.start();
						} }
					>
						<span className="flw-help__label">
							{ __( 'Take the tour', 'rave-payment-forms' ) }
						</span>
						<span className="flw-help__hint">
							{ __(
								'A quick walk through this page.',
								'rave-payment-forms'
							) }
						</span>
					</button>
				) }

				<div className="flw-help__row">
					<span>
						<span className="flw-help__label" id="flw-help-tips">
							{ __( 'Show tips', 'rave-payment-forms' ) }
						</span>
						<span className="flw-help__hint">
							{ __(
								'Guide me the first time I open each page.',
								'rave-payment-forms'
							) }
						</span>
					</span>
					<Switch
						checked={ tour.enabled }
						label={ __( 'Show tips', 'rave-payment-forms' ) }
						onChange={ ( enabled ) => {
							close();
							tour.setEnabled( enabled );
						} }
					/>
				</div>

				{ adminData().documentation && (
					<a
						className="flw-help__link"
						href={ adminData().documentation }
						target="_blank"
						rel="noreferrer noopener"
					>
						{ __(
							'Flutterwave documentation',
							'rave-payment-forms'
						) }
						<ExternalIcon />
					</a>
				) }
			</div>
		</Popover>
	);
};
