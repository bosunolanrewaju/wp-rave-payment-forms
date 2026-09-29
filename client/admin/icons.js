/**
 * Inline SVG marks and illustrations.
 *
 * The two welcome-card illustrations are the real Figma exports, loaded from
 * assets/img/admin/. The remaining marks and the success artwork are still
 * placeholders drawn to the right proportions and colours, so layout is final
 * even though the artwork is stand-in. See assets/img/admin/README.md for how
 * to swap the rest in.
 */

import { createElement as el } from '@wordpress/element';
import { adminData } from './lib/api';

const svg = ( props, ...children ) =>
	el(
		'svg',
		{
			xmlns: 'http://www.w3.org/2000/svg',
			focusable: 'false',
			'aria-hidden': 'true',
			...props,
		},
		...children
	);

/**
 * The official Flutterwave logo, served from assets/images/admin/.
 *
 * @return {Element} The logo.
 */
export const FlutterwaveWordmark = () =>
	el( 'img', {
		className: 'flw-wordmark',
		src: adminData().assetsUrl + 'flutterwave-logo.svg',
		alt: 'Flutterwave',
		width: 168,
		height: 40,
	} );

export const ChevronLeft = () =>
	svg(
		{ viewBox: '0 0 16 16', width: '16', height: '16' },
		el( 'path', {
			d: 'M10 3 5 8l5 5',
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: '1.6',
			strokeLinecap: 'round',
			strokeLinejoin: 'round',
		} )
	);

export const ChevronDown = () =>
	svg(
		{ viewBox: '0 0 16 16', width: '16', height: '16' },
		el( 'path', {
			d: 'm4 6 4 4 4-4',
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: '1.6',
			strokeLinecap: 'round',
			strokeLinejoin: 'round',
		} )
	);

export const InfoIcon = () =>
	svg(
		{ viewBox: '0 0 16 16', width: '15', height: '15' },
		el( 'circle', {
			cx: '8',
			cy: '8',
			r: '7',
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: '1.2',
		} ),
		el( 'path', {
			d: 'M8 7.2v4',
			stroke: 'currentColor',
			strokeWidth: '1.4',
			strokeLinecap: 'round',
		} ),
		el( 'circle', { cx: '8', cy: '4.9', r: '0.9', fill: 'currentColor' } )
	);

export const CheckIcon = () =>
	svg(
		{ viewBox: '0 0 16 16', width: '12', height: '12' },
		el( 'path', {
			d: 'm3.5 8.4 3 3 6-6.8',
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: '2',
			strokeLinecap: 'round',
			strokeLinejoin: 'round',
		} )
	);

/* -------------------------------------------------------------------------- */
/* Illustrations                                                              */
/* -------------------------------------------------------------------------- */

/**
 * The welcome cards use the exported Figma artwork. Each file is 370x190 and
 * carries its own rounded background, so the card wrapper adds none of its own.
 *
 * @param {string} file Filename inside assets/img/admin/.
 * @return {Function} An illustration component.
 */
const exported = ( file ) => () =>
	el( 'img', {
		className: 'flw-illustration',
		src: adminData().assetsUrl + file,
		alt: '',
		width: 370,
		height: 190,
		loading: 'lazy',
	} );

export const DocsIllustration = exported( 'welcome-docs.svg' );

export const SupportIllustration = exported( 'welcome-support.svg' );

export const SuccessIllustration = () =>
	svg(
		{ viewBox: '0 0 120 120', className: 'flw-success-art' },
		el( 'circle', { cx: '60', cy: '60', r: '58', fill: '#FFF4E3' } ),
		el( 'rect', {
			x: '36',
			y: '30',
			width: '44',
			height: '58',
			rx: '6',
			fill: '#FF7A00',
		} ),
		el( 'rect', {
			x: '44',
			y: '42',
			width: '28',
			height: '3.5',
			rx: '1.75',
			fill: '#FFE0B8',
		} ),
		el( 'rect', {
			x: '44',
			y: '52',
			width: '28',
			height: '3.5',
			rx: '1.75',
			fill: '#FFE0B8',
		} ),
		el( 'rect', {
			x: '44',
			y: '62',
			width: '18',
			height: '3.5',
			rx: '1.75',
			fill: '#FFE0B8',
		} ),
		el( 'circle', { cx: '82', cy: '82', r: '14', fill: '#12B76A' } ),
		el( 'path', {
			d: 'm76 82 4 4 8-8',
			fill: 'none',
			stroke: '#fff',
			strokeWidth: '2.4',
			strokeLinecap: 'round',
			strokeLinejoin: 'round',
		} )
	);

/* -------------------------------------------------------------------------- */
/* Payment method marks                                                       */
/* -------------------------------------------------------------------------- */

const mark = ( ...children ) =>
	svg( { viewBox: '0 0 48 32', className: 'flw-method-mark' }, ...children );

export const METHOD_MARKS = {
	card: () =>
		mark(
			el( 'rect', {
				x: '6',
				y: '8',
				width: '36',
				height: '17',
				rx: '3',
				fill: '#1D2939',
			} ),
			el( 'rect', {
				x: '6',
				y: '12.5',
				width: '36',
				height: '4',
				fill: '#98A2B3',
			} ),
			el( 'rect', {
				x: '9.5',
				y: '19',
				width: '10',
				height: '2.5',
				rx: '1.25',
				fill: '#98A2B3',
			} )
		),
	stablecoin: () =>
		mark(
			el( 'circle', { cx: '19', cy: '16', r: '9', fill: '#26A17B' } ),
			el( 'circle', {
				cx: '29',
				cy: '16',
				r: '9',
				fill: '#2775CA',
				opacity: '0.9',
			} ),
			el( 'path', {
				d: 'M26 12.5h6M29 12.5V20',
				stroke: '#fff',
				strokeWidth: '1.6',
				strokeLinecap: 'round',
			} )
		),
	banktransfer: () =>
		mark(
			el( 'path', { d: 'M24 6 40 14H8L24 6Z', fill: '#1D2939' } ),
			el( 'rect', {
				x: '11',
				y: '16',
				width: '3.5',
				height: '8',
				fill: '#1D2939',
			} ),
			el( 'rect', {
				x: '22.25',
				y: '16',
				width: '3.5',
				height: '8',
				fill: '#1D2939',
			} ),
			el( 'rect', {
				x: '33.5',
				y: '16',
				width: '3.5',
				height: '8',
				fill: '#1D2939',
			} ),
			el( 'rect', {
				x: '8',
				y: '25',
				width: '32',
				height: '3',
				rx: '1.5',
				fill: '#1D2939',
			} )
		),
	mobilemoney: () =>
		mark(
			el( 'rect', {
				x: '15',
				y: '4',
				width: '18',
				height: '25',
				rx: '3',
				fill: '#1D2939',
			} ),
			el( 'rect', {
				x: '17.5',
				y: '8',
				width: '13',
				height: '15',
				rx: '1.5',
				fill: '#FFC46B',
			} ),
			el( 'circle', { cx: '24', cy: '26', r: '1.4', fill: '#98A2B3' } ),
			el( 'path', {
				d: 'M22 15.5h4M24 13.5v4',
				stroke: '#1D2939',
				strokeWidth: '1.4',
				strokeLinecap: 'round',
			} )
		),
	applepay: () =>
		mark(
			el( 'rect', {
				x: '2',
				y: '4',
				width: '44',
				height: '24',
				rx: '5',
				fill: '#fff',
				stroke: '#D0D5DD',
			} ),
			el( 'path', {
				d: 'M16.4 12.2c-.5.6-1.3 1.1-2.1 1-.1-.8.3-1.7.8-2.2.5-.6 1.4-1 2.1-1.1.1.9-.3 1.7-.8 2.3Zm.8 1.2c-1.2-.1-2.2.7-2.7.7-.6 0-1.4-.6-2.3-.6-1.2 0-2.3.7-2.9 1.8-1.2 2.1-.3 5.3.9 7 .6.9 1.3 1.8 2.2 1.8.9 0 1.2-.6 2.3-.6s1.4.6 2.3.6c1 0 1.6-.8 2.2-1.7.7-1 1-1.9 1-2-.1 0-1.9-.7-1.9-2.9 0-1.8 1.4-2.6 1.5-2.7-.8-1.2-2.1-1.3-2.6-1.4Z',
				fill: '#111',
			} ),
			el(
				'text',
				{
					x: '24',
					y: '21',
					fill: '#111',
					fontFamily: '-apple-system, Helvetica, Arial, sans-serif',
					fontSize: '13',
					fontWeight: '600',
				},
				'Pay'
			)
		),
	googlepay: () =>
		mark(
			el( 'rect', {
				x: '2',
				y: '4',
				width: '44',
				height: '24',
				rx: '5',
				fill: '#fff',
				stroke: '#D0D5DD',
			} ),
			el( 'path', {
				d: 'M18.6 15.6v3h-1.5v-7.4h2.6c.6 0 1.2.2 1.6.7.5.4.7 1 .7 1.5 0 .6-.2 1.1-.7 1.5-.4.4-1 .7-1.6.7h-1.1Z',
				fill: '#5F6368',
			} ),
			el( 'path', {
				d: 'M14.4 14.4v1.3h2.2c-.1.6-.4 1-.8 1.3-.4.3-.9.5-1.4.5-1.2 0-2.2-1-2.2-2.2s1-2.2 2.2-2.2c.6 0 1.1.2 1.5.6l1-1a3.6 3.6 0 0 0-2.5-1 3.6 3.6 0 1 0 0 7.2c1 0 1.9-.3 2.5-1 .6-.6 .9-1.5 .9-2.5v-.6h-3.4Z',
				fill: '#4285F4',
			} ),
			el(
				'text',
				{
					x: '26',
					y: '20',
					fill: '#5F6368',
					fontFamily: 'Arial, sans-serif',
					fontSize: '12',
					fontWeight: '600',
				},
				'Pay'
			)
		),
	opay: () =>
		mark(
			el( 'rect', {
				x: '2',
				y: '4',
				width: '44',
				height: '24',
				rx: '5',
				fill: '#fff',
				stroke: '#D0D5DD',
			} ),
			el( 'circle', {
				cx: '13',
				cy: '16',
				r: '5.5',
				fill: 'none',
				stroke: '#12B76A',
				strokeWidth: '2.4',
			} ),
			el(
				'text',
				{
					x: '20',
					y: '21',
					fill: '#101B4C',
					fontFamily: 'Arial, sans-serif',
					fontSize: '12',
					fontWeight: '700',
				},
				'Pay'
			)
		),
};

export const FilterIcon = () =>
	svg(
		{ viewBox: '0 0 16 16', width: '14', height: '14' },
		el( 'path', {
			d: 'M2.5 3h11l-4.25 5v4.25L6.75 13.5V8L2.5 3Z',
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: '1.4',
			strokeLinejoin: 'round',
		} )
	);

export const DownloadIcon = () =>
	svg(
		{ viewBox: '0 0 16 16', width: '14', height: '14' },
		el( 'path', {
			d: 'M8 2.5v7.5m0 0L5 7m3 3 3-3M3 11.5V13h10v-1.5',
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: '1.4',
			strokeLinecap: 'round',
			strokeLinejoin: 'round',
		} )
	);

export const PlusIcon = () =>
	svg(
		{ viewBox: '0 0 16 16', width: '14', height: '14' },
		el( 'path', {
			d: 'M8 3v10M3 8h10',
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: '1.6',
			strokeLinecap: 'round',
		} )
	);

export const ExternalIcon = () =>
	svg(
		{ viewBox: '0 0 16 16', width: '12', height: '12' },
		el( 'path', {
			d: 'M9.5 2.5h4v4m0-4L7 9m-1-5.5H3.5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V10',
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: '1.3',
			strokeLinecap: 'round',
			strokeLinejoin: 'round',
		} )
	);

export const HelpIcon = () =>
	svg(
		{ viewBox: '0 0 20 20', width: '18', height: '18' },
		el( 'circle', {
			cx: '10',
			cy: '10',
			r: '8.25',
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: '1.5',
		} ),
		el( 'path', {
			d: 'M7.75 7.6a2.3 2.3 0 0 1 4.45.8c0 1.5-2.2 1.9-2.2 3.1',
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: '1.5',
			strokeLinecap: 'round',
		} ),
		el( 'circle', {
			cx: '10',
			cy: '14.4',
			r: '0.95',
			fill: 'currentColor',
		} )
	);
