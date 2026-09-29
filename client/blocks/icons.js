/**
 * Block icons.
 */

import { SVG, Path, Rect, Circle } from '@wordpress/primitives';

const ORANGE = '#ff9b00';

export const buttonIcon = (
	<SVG viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
		<Rect x="2" y="7" width="20" height="10" rx="3" fill={ ORANGE } />
		<Path
			d="M8 12h8"
			stroke="#1a1a1a"
			strokeWidth="1.8"
			strokeLinecap="round"
		/>
	</SVG>
);

export const formIcon = (
	<SVG viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
		<Rect
			x="4"
			y="2.5"
			width="16"
			height="19"
			rx="2.5"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.5"
		/>
		<Path
			d="M7.5 7h9M7.5 11h9"
			stroke="currentColor"
			strokeWidth="1.5"
			strokeLinecap="round"
		/>
		<Rect x="7" y="15" width="10" height="3.5" rx="1.2" fill={ ORANGE } />
	</SVG>
);

export const donationIcon = (
	<SVG viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
		<Path
			d="M12 20.5s-7.5-4.4-7.5-10A4.3 4.3 0 0 1 12 7.8a4.3 4.3 0 0 1 7.5 2.7c0 5.6-7.5 10-7.5 10Z"
			fill={ ORANGE }
		/>
	</SVG>
);

export const pricingIcon = (
	<SVG viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
		<Rect
			x="4"
			y="2.5"
			width="16"
			height="19"
			rx="2.5"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.5"
		/>
		<Path
			d="M8 7.5h5"
			stroke="currentColor"
			strokeWidth="1.5"
			strokeLinecap="round"
		/>
		<Path
			d="M8 11.5h8"
			stroke={ ORANGE }
			strokeWidth="2.5"
			strokeLinecap="round"
		/>
		<Circle cx="8.5" cy="16" r="1" fill="currentColor" />
		<Path
			d="M11 16h5"
			stroke="currentColor"
			strokeWidth="1.5"
			strokeLinecap="round"
		/>
	</SVG>
);

export const methodsIcon = (
	<SVG viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
		<Rect
			x="2.5"
			y="6"
			width="19"
			height="13"
			rx="2"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.5"
		/>
		<Path d="M2.5 10h19" stroke="currentColor" strokeWidth="1.5" />
		<Rect x="5.5" y="13.5" width="5" height="2.5" rx="1" fill={ ORANGE } />
	</SVG>
);
