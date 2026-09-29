/**
 * Root of the Flutterwave admin app.
 *
 * Owns the settings state and decides which surface to show: the welcome
 * screen for a fresh install, the wizard once activation starts, and the
 * tabbed settings screen for anyone already configured.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { LoadingScreen, Toast } from './components';
import Welcome from './screens/Welcome';
import Wizard from './screens/Wizard';
import Success from './screens/Success';
import Settings from './screens/Settings';
import { fetchSettings } from './lib/api';

const VIEW = {
	LOADING: 'loading',
	WELCOME: 'welcome',
	WIZARD: 'wizard',
	SUCCESS: 'success',
	SETTINGS: 'settings',
};

const App = () => {
	const [ view, setView ] = useState( VIEW.LOADING );
	const [ values, setValues ] = useState( null );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		let cancelled = false;

		fetchSettings()
			.then( ( payload ) => {
				if ( cancelled ) {
					return;
				}
				setValues( payload );
				setView(
					payload.onboardingComplete ? VIEW.SETTINGS : VIEW.WELCOME
				);
			} )
			.catch( ( error ) => {
				if ( cancelled ) {
					return;
				}
				setNotice( {
					status: 'error',
					message:
						error?.message ||
						__(
							'Could not load your Flutterwave settings.',
							'rave-payment-forms'
						),
				} );
				setView( VIEW.WELCOME );
				setValues( ( current ) => current || {} );
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	const setValue = useCallback( ( key, value ) => {
		setValues( ( current ) => ( { ...current, [ key ]: value } ) );
	}, [] );

	// The API echoes the persisted state back, so trust it over local edits.
	const onSaved = useCallback( ( payload ) => setValues( payload ), [] );

	const onNotice = useCallback(
		( status, message ) => setNotice( { status, message } ),
		[]
	);

	useEffect( () => {
		if ( ! notice ) {
			return undefined;
		}

		const timer = setTimeout( () => setNotice( null ), 4000 );
		return () => clearTimeout( timer );
	}, [ notice ] );

	if ( VIEW.LOADING === view || ! values ) {
		return <LoadingScreen />;
	}

	return (
		<>
			{ notice && (
				<Toast
					status={ notice.status }
					message={ notice.message }
					onDismiss={ () => setNotice( null ) }
				/>
			) }

			{ VIEW.WELCOME === view && (
				<Welcome onActivate={ () => setView( VIEW.WIZARD ) } />
			) }

			{ VIEW.WIZARD === view && (
				<Wizard
					values={ values }
					setValue={ setValue }
					onSaved={ onSaved }
					onFinished={ () => setView( VIEW.SUCCESS ) }
					onError={ ( message ) => onNotice( 'error', message ) }
				/>
			) }

			{ VIEW.SUCCESS === view && (
				<Success onDone={ () => setView( VIEW.SETTINGS ) } />
			) }

			{ VIEW.SETTINGS === view && (
				<Settings
					values={ values }
					setValue={ setValue }
					onSaved={ onSaved }
					onNotice={ onNotice }
				/>
			) }
		</>
	);
};

export default App;
